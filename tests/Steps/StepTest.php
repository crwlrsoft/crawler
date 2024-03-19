<?php

namespace tests\Steps;

use Crwlr\Crawler\Input;
use Crwlr\Crawler\Loader\Http\Messages\RespondedRequest;
use Crwlr\Crawler\Logger\CliLogger;
use Crwlr\Crawler\Output;
use Crwlr\Crawler\Result;
use Crwlr\Crawler\Steps\Filters\Filter;
use Crwlr\Crawler\Steps\Refiners\StringRefiner;
use Crwlr\Crawler\Steps\Step;
use Exception;
use Generator;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

use stdClass;

use function tests\helper_getInputReturningStep;
use function tests\helper_getStdClassWithData;
use function tests\helper_getStepYieldingMultipleArraysWithNumber;
use function tests\helper_getStepYieldingMultipleNumbers;
use function tests\helper_getStepYieldingMultipleObjectsWithNumber;
use function tests\helper_getValueReturningStep;
use function tests\helper_invokeStepWithInput;
use function tests\helper_traverseIterable;

/** @var TestCase $this */

test('You can add a logger and it is available within the invoke method', function () {
    $step = new class () extends Step {
        /**
         * @return Generator<string>
         */
        protected function invoke(mixed $input): Generator
        {
            $this->logger?->info('logging works');

            yield 'something';
        }
    };

    $step->addLogger(new CliLogger());

    helper_traverseIterable($step->invokeStep(new Input('test')));

    $output = $this->getActualOutputForAssertion();

    expect($output)->toContain('logging works');
});

test(
    'The invokeStep method wraps the values returned by invoke in Output objects by default without Result objects',
    function () {
        $step = helper_getValueReturningStep('returnValue');

        $output = helper_invokeStepWithInput($step);

        expect($output)->toHaveCount(1);

        expect($output[0])->toBeInstanceOf(Output::class);

        expect($output[0]->get())->toBe('returnValue');

        expect($output[0]->result)->toBeNull();
    }
);

test(
    'The invokeStep method creates a Result object that is added to the Output when you set a property name',
    function () {
        $step = helper_getValueReturningStep('returnValue')
            ->addToResult('property');

        $output = helper_invokeStepWithInput($step);

        expect($output[0]->result)->toBeInstanceOf(Result::class);

        expect($output[0]->result?->toArray())->toBe(['property' => 'returnValue']);
    }
);

it('creates a Result object with the data from yielded array when addToResult() is used', function () {
    $step = helper_getValueReturningStep(['foo' => 'bar', 'baz' => 'yo'])
        ->addToResult();

    $output = helper_invokeStepWithInput($step);

    expect($output[0]->result)->toBeInstanceOf(Result::class);

    expect($output[0]->result?->toArray())->toBe(['foo' => 'bar', 'baz' => 'yo']);
});

it('picks keys from the output array when you pass an array of keys to addToResult()', function () {
    $step = helper_getValueReturningStep(['user' => 'otsch', 'firstname' => 'Christian', 'surname' => 'Olear'])
        ->addToResult(['firstname', 'surname']);

    $output = helper_invokeStepWithInput($step);

    expect($output[0]->result)->toBeInstanceOf(Result::class);

    expect($output[0]->result?->toArray())->toBe(['firstname' => 'Christian', 'surname' => 'Olear']);
});

it('is able to pick keys from nested (array) output using dot notation', function () {
    $step = helper_getValueReturningStep([
        'users' => [
            ['user' => 'otsch', 'firstname' => 'Christian', 'surname' => 'Olear'],
            ['user' => 'juerx', 'firstname' => 'Jürgen', 'surname' => 'Müller'],
            ['user' => 'sandy', 'firstname' => 'Sandra', 'surname' => 'Mayr'],
        ],
        'foo' => 'bar',
    ])
        ->addToResult(['nickname' => 'users.0.user', 'foo']);

    $output = helper_invokeStepWithInput($step);

    expect($output[0]->result)->toBeInstanceOf(Result::class);

    expect($output[0]->result?->toArray())->toBe(['foo' => 'bar', 'nickname' => 'otsch']);
});

it('picks keys from nested output including a RespondedRequest object', function () {
    $step = helper_getValueReturningStep([
        'response' => new RespondedRequest(
            new Request('GET', 'https://www.example.com/something'),
            new Response(200, body: 'Hi :)'),
        ),
        'foo' => 'bar',
    ])
        ->addToResult(['content' => 'response.body']);

    $output = helper_invokeStepWithInput($step);

    expect($output[0]->result)->toBeInstanceOf(Result::class);

    expect($output[0]->result?->toArray())->toBe(['content' => 'Hi :)']);
});

it('maps output keys to different result keys when defined in the array passed to addToResult()', function () {
    $step = helper_getValueReturningStep(['user' => 'otsch', 'firstname' => 'Christian', 'surname' => 'Olear'])
        ->addToResult(['foo' => 'firstname', 'bar' => 'surname']);

    $output = helper_invokeStepWithInput($step);

    expect($output[0]->result)->toBeInstanceOf(Result::class);

    expect($output[0]->result?->toArray())->toBe(['foo' => 'Christian', 'bar' => 'Olear']);
});

test(
    'The addsToOrCreatesResult() method returns false when addToResult() and addLaterToResult() have not been called',
    function () {
        $step = helper_getValueReturningStep('lol');

        expect($step->addsToOrCreatesResult())->toBeFalse();
    }
);

test('The addsToOrCreatesResult() method returns true when addToResult() was called with a string key', function () {
    $step = helper_getValueReturningStep('test')->addToResult('test');

    expect($step->addsToOrCreatesResult())->toBeTrue();
});

test('The addsToOrCreatesResult() method returns true when addLaterToResult() was called with a string key', function () {
    $step = helper_getValueReturningStep('test')->addLaterToResult('test');

    expect($step->addsToOrCreatesResult())->toBeTrue();
});

test('The addsToOrCreatesResult() method returns true when addToResult() was called without an argument', function () {
    $step = helper_getValueReturningStep(['test' => 'yo'])->addToResult();

    expect($step->addsToOrCreatesResult())->toBeTrue();
});

test(
    'The addsToOrCreatesResult() method returns true when addLaterToResult() was called without an argument',
    function () {
        $step = helper_getValueReturningStep(['test' => 'yo'])->addLaterToResult();

        expect($step->addsToOrCreatesResult())->toBeTrue();
    }
);

test('The createsResult() method returns false when addToResult() has not been called', function () {
    $step = helper_getValueReturningStep('lol');

    expect($step->createsResult())->toBeFalse();
});

test('The createsResult() method returns true when addToResult() was called with a string key', function () {
    $step = helper_getValueReturningStep('test')->addToResult('test');

    expect($step->createsResult())->toBeTrue();
});

test('The createsResult() method returns false when addLaterToResult() was called with a string key', function () {
    $step = helper_getValueReturningStep('test')->addLaterToResult('test');

    expect($step->createsResult())->toBeFalse();
});

test('The createsResult() method returns true when addToResult() was called without an argument', function () {
    $step = helper_getValueReturningStep(['test' => 'yo'])->addToResult();

    expect($step->createsResult())->toBeTrue();
});

test('The createsResult() method returns false when addLaterToResult() was called without an argument', function () {
    $step = helper_getValueReturningStep(['test' => 'yo'])->addLaterToResult();

    expect($step->createsResult())->toBeFalse();
});

it('uses a key from array input when defined', function () {
    $step = helper_getInputReturningStep()->useInputKey('bar');

    $output = helper_invokeStepWithInput($step, new Input(
        ['foo' => 'fooValue', 'bar' => 'barValue', 'baz' => 'bazValue']
    ));

    expect($output)->toHaveCount(1);

    expect($output[0]->get())->toBe('barValue');
});

it('logs a warning message when the input key to use does not exist in input array', function () {
    $step = helper_getInputReturningStep()->useInputKey('baz');

    $step->addLogger(new CliLogger());

    $output = helper_invokeStepWithInput($step, new Input(['foo' => 'one', 'bar' => 'two']));

    expect($output)->toHaveCount(0)
        ->and($this->getActualOutputForAssertion())
        ->toContain('Can\'t get key from input, because it does not exist.');
});

it(
    'logs a warning message when useInputKey() was called but the input value is not an array',
    function (mixed $inputValue) {
        $step = helper_getInputReturningStep()->useInputKey('baz');

        $step->addLogger(new CliLogger());

        $output = helper_invokeStepWithInput($step, new Input($inputValue));

        expect($output)->toHaveCount(0)
            ->and($this->getActualOutputForAssertion())
            ->toContain(
                'Can\'t get key from input, because input is of type ' . gettype($inputValue) . ' instead of array.'
            );
    }
)->with([
    ['string'],
    [0],
    [new stdClass()],
]);

it('doesn\'t add the result object to the Input object only to the Output', function () {
    $step = helper_getValueReturningStep('Stand with Ukraine!')
        ->addToResult('property');

    $input = new Input('inputValue');

    $output = helper_invokeStepWithInput($step);

    expect($output[0]->result)->toBeInstanceOf(Result::class);

    expect($input->result)->toBe(null);
});

it('appends properties to a result object that was already included with the Input object', function () {
    $step = helper_getValueReturningStep('returnValue')
        ->addToResult('property');

    $prevResult = new Result();

    $prevResult->set('prevProperty', 'foobar');

    $input = new Input('inputValue', $prevResult);

    $output = helper_invokeStepWithInput($step, $input);

    expect($output[0]->result)->toBeInstanceOf(Result::class);

    expect($output[0]->result?->toArray())->toBe([
        'prevProperty' => 'foobar',
        'property' => 'returnValue',
    ]);
});

it(
    'adds a secondary Result object with data to add later to main Result objects when addLaterToResult() is called',
    function () {
        $step = helper_getValueReturningStep('returnValue')
            ->addLaterToResult('property');

        $outputs = helper_invokeStepWithInput($step);

        expect($outputs[0]->result)->toBeNull();

        expect($outputs[0]->addLaterToResult)->toBeInstanceOf(Result::class);

        expect($outputs[0]->addLaterToResult?->toArray())->toBe([
            'property' => 'returnValue',
        ]);
    }
);

test('addLaterToResult() works with array output and no argument', function () {
    $step = helper_getValueReturningStep(['foo' => 'bar'])
        ->addLaterToResult();

    $outputs = helper_invokeStepWithInput($step);

    expect($outputs[0]->result)->toBeNull();

    expect($outputs[0]->addLaterToResult)->toBeInstanceOf(Result::class);

    expect($outputs[0]->addLaterToResult?->toArray())->toBe([
        'foo' => 'bar',
    ]);
});

test('with addLaterToResult() you can also pick some keys from array output', function () {
    $step = helper_getValueReturningStep(['foo' => 'one', 'bar' => 'two', 'baz' => 'three', 'quz' => 'four'])
        ->addLaterToResult(['foo', 'baz', 'yolo']);

    $outputs = helper_invokeStepWithInput($step);

    expect($outputs[0]->result)->toBeNull();

    expect($outputs[0]->addLaterToResult)->toBeInstanceOf(Result::class);

    expect($outputs[0]->addLaterToResult?->toArray())->toBe([
        'foo' => 'one',
        'baz' => 'three',
    ]);
});

it('does not lose previously added result to add later, when it uses the useInputKey() method', function () {
    $step = helper_getValueReturningStep(['test' => 'test'])
        ->useInputKey('foo');

    $addLaterToResult = new Result();

    $outputs = helper_invokeStepWithInput($step, new Input(['foo' => 'test'], addLaterToResult: $addLaterToResult));

    expect($outputs[0]->addLaterToResult)->toBe($addLaterToResult);
});

it(
    'also passes on Result objects through further steps when they don\'t define further result resource properties',
    function () {
        $step = helper_getValueReturningStep('returnValue');

        $prevResult = new Result();

        $prevResult->set('prevProperty', 'foobar');

        $output = helper_invokeStepWithInput($step, new Input('inputValue', $prevResult));

        expect($output[0]->result)->toBeInstanceOf(Result::class);

        expect($output[0]->result?->toArray())->toBe(['prevProperty' => 'foobar']);
    }
);

it('doesn\'t invoke twice with duplicate inputs when uniqueInput was called', function () {
    $step = helper_getInputReturningStep();

    $outputs = helper_invokeStepWithInput($step, 'foo');

    expect($outputs)->toHaveCount(1);

    $outputs = helper_invokeStepWithInput($step, 'foo');

    expect($outputs)->toHaveCount(1);

    $step->uniqueInputs();

    $outputs = helper_invokeStepWithInput($step, 'foo');

    expect($outputs)->toHaveCount(1);

    $outputs = helper_invokeStepWithInput($step, 'foo');

    expect($outputs)->toHaveCount(0);
});

it(
    'doesn\'t invoke twice with inputs with the same value in an array key when uniqueInput was called with that key',
    function () {
        $step = helper_getInputReturningStep();

        $step->uniqueInputs();

        $outputs = helper_invokeStepWithInput($step, ['foo' => 'bar', 'number' => 1]);

        expect($outputs)->toHaveCount(1);

        $outputs = helper_invokeStepWithInput($step, ['foo' => 'bar', 'number' => 2]);

        expect($outputs)->toHaveCount(1);

        $step->resetAfterRun();

        $step->uniqueInputs('foo');

        $outputs = helper_invokeStepWithInput($step, ['foo' => 'bar', 'number' => 1]);

        expect($outputs)->toHaveCount(1);

        $outputs = helper_invokeStepWithInput($step, ['foo' => 'bar', 'number' => 2]);

        expect($outputs)->toHaveCount(0);
    }
);

it(
    'doesn\'t invoke twice with inputs with the same value in an object key when uniqueInput was called with that key',
    function () {
        $step = helper_getInputReturningStep();

        $step->uniqueInputs();

        $outputs = helper_invokeStepWithInput($step, helper_getStdClassWithData(['foo' => 'bar', 'number' => 1]));

        expect($outputs)->toHaveCount(1);

        $outputs = helper_invokeStepWithInput($step, helper_getStdClassWithData(['foo' => 'bar', 'number' => 2]));

        expect($outputs)->toHaveCount(1);

        $step->resetAfterRun();

        $step->uniqueInputs('foo');

        $outputs = helper_invokeStepWithInput($step, helper_getStdClassWithData(['foo' => 'bar', 'number' => 1]));

        expect($outputs)->toHaveCount(1);

        $outputs = helper_invokeStepWithInput($step, helper_getStdClassWithData(['foo' => 'bar', 'number' => 2]));

        expect($outputs)->toHaveCount(0);
    }
);

it('makes outputs unique when uniqueOutput was called', function () {
    $step = helper_getStepYieldingMultipleNumbers();

    $step->uniqueOutputs();

    $output = helper_invokeStepWithInput($step, new Input('anything'));

    expect($output)->toHaveCount(5);

    expect($output[0]->get())->toBe('one');

    expect($output[1]->get())->toBe('two');

    expect($output[2]->get())->toBe('three');

    expect($output[3]->get())->toBe('four');

    expect($output[4]->get())->toBe('five');
});

it('makes outputs unique when providing a key name to uniqueOutput to use from array output', function () {
    $step = helper_getStepYieldingMultipleArraysWithNumber();

    $step->uniqueOutputs('number');

    $output = helper_invokeStepWithInput($step, new Input('anything'));

    expect($output)->toHaveCount(5);
});

it('makes outputs unique when providing a key name to uniqueOutput to use from object output', function () {
    $step = helper_getStepYieldingMultipleObjectsWithNumber();

    $step->uniqueOutputs('number');

    $output = helper_invokeStepWithInput($step, new Input('anything'));

    expect($output)->toHaveCount(5);
});

it('makes array outputs unique when providing no key name to uniqueOutput', function () {
    $step = helper_getStepYieldingMultipleArraysWithNumber();

    $step->uniqueOutputs();

    $output = helper_invokeStepWithInput($step, new Input(false));

    expect($output)->toHaveCount(5);

    $output = helper_invokeStepWithInput($step, new Input(true));

    expect($output)->toHaveCount(8);
});

it('makes object outputs unique when providing no key name to uniqueOutput', function () {
    $step = helper_getStepYieldingMultipleArraysWithNumber();

    $step->uniqueOutputs();

    $output = helper_invokeStepWithInput($step, new Input(false));

    expect($output)->toHaveCount(5);

    $output = helper_invokeStepWithInput($step, new Input(true));

    expect($output)->toHaveCount(8);
});

it('calls the validateAndSanitizeInput method', function () {
    $step = new class () extends Step {
        protected function validateAndSanitizeInput(mixed $input): string
        {
            return $input . ' validated and sanitized';
        }

        protected function invoke(mixed $input): Generator
        {
            yield $input;
        }
    };

    $output = helper_invokeStepWithInput($step, 'inputValue');

    expect($output[0]->get())->toBe('inputValue validated and sanitized');
});

test(
    'when calling validateAndSanitizeStringOrStringable() and the input is array with a single element it tries to ' .
    'use that element as input value',
    function () {
        $step = new class () extends Step {
            protected function validateAndSanitizeInput(mixed $input): string
            {
                return $this->validateAndSanitizeStringOrStringable($input);
            }

            protected function invoke(mixed $input): Generator
            {
                yield $input;
            }
        };

        $output = helper_invokeStepWithInput($step, ['inputValue']);

        expect($output[0]->get())->toBe('inputValue');
    }
);

test(
    'when calling validateAndSanitizeStringOrStringable() and the input is array with multiple elements it throws ' .
    'an InvalidArgumentException',
    function () {
        $step = new class () extends Step {
            protected function validateAndSanitizeInput(mixed $input): string
            {
                return $this->validateAndSanitizeStringOrStringable($input);
            }

            protected function invoke(mixed $input): Generator
            {
                yield $input;
            }
        };

        helper_invokeStepWithInput($step, ['inputValue', 'foo' => 'bar']);
    }
)->throws(InvalidArgumentException::class);

it('is possible that a step does not produce any output at all', function () {
    $step = new class () extends Step {
        protected function invoke(mixed $input): Generator
        {
            if ($input === 'foo') {
                yield 'bar';
            }
        }
    };

    $output = helper_invokeStepWithInput($step, 'lol');

    expect($output)->toHaveCount(0);

    $output = helper_invokeStepWithInput($step, 'foo');

    expect($output)->toHaveCount(1);

    expect($output[0]->get())->toBe('bar');
});

test('You can add and call an updateInputUsingOutput callback', function () {
    $step = helper_getValueReturningStep('something');

    $step->updateInputUsingOutput(function (mixed $input, mixed $output) {
        return $input . ' ' . $output;
    });

    $updatedInput = $step->callUpdateInputUsingOutput(new Input('Boo'), new Output('Yah!'));

    expect($updatedInput)->toBeInstanceOf(Input::class);

    expect($updatedInput->get())->toBe('Boo Yah!');
});

it('does not lose previously added result to add later, when updateInputUsingOutput() is called', function () {
    $step = helper_getValueReturningStep('something');

    $step->updateInputUsingOutput(function (mixed $input, mixed $output) {
        return $input . ' ' . $output;
    });

    $addLaterToResult = new Result();

    $updatedInput = $step->callUpdateInputUsingOutput(
        new Input('Some', addLaterToResult: $addLaterToResult),
        new Output('thing')
    );

    expect($updatedInput->addLaterToResult)->toBe($addLaterToResult);
});

it('does not yield more outputs than defined via maxOutputs() method', function () {
    $step = helper_getValueReturningStep('yolo')->maxOutputs(3);

    for ($i = 1; $i <= 5; $i++) {
        $outputs = helper_invokeStepWithInput($step, new Input('asdf'));

        if ($i <= 3) {
            expect($outputs)->toHaveCount(1);
        } else {
            expect($outputs)->toHaveCount(0);
        }
    }
});

it(
    'does not yield more outputs than defined via maxOutputs() when step yields multiple outputs per input and the ' .
    'limit is reached in the middle of the outputs resulting from one input',
    function () {
        $step = new class () extends Step {
            protected function invoke(mixed $input): Generator
            {
                yield 'one';

                yield 'two';

                yield 'three';
            }
        };

        $step->maxOutputs(7);

        $outputs = helper_invokeStepWithInput($step, new Input('a'));

        expect($outputs)->toHaveCount(3);

        $outputs = helper_invokeStepWithInput($step, new Input('b'));

        expect($outputs)->toHaveCount(3);

        $outputs = helper_invokeStepWithInput($step, new Input('c'));

        expect($outputs)->toHaveCount(1);
    }
);

test('When a step has max outputs defined, it won\'t call the invoke method after the limit was reached', function () {
    $step = new class () extends Step {
        public int $_invokeCallCount = 0;

        protected function invoke(mixed $input): Generator
        {
            $this->_invokeCallCount += 1;

            yield 'something';
        }
    };

    $step->maxOutputs(2);

    helper_invokeStepWithInput($step, new Input('one'));

    helper_invokeStepWithInput($step, new Input('two'));

    helper_invokeStepWithInput($step, new Input('three'));

    helper_invokeStepWithInput($step, new Input('four'));

    expect($step->_invokeCallCount)->toBe(2);
});

it('resets outputs count for maxOutputs rule when resetAfterRun() is called', function () {
    $step = helper_getValueReturningStep('gogogo')->maxOutputs(2);

    helper_invokeStepWithInput($step, new Input('one'));

    helper_invokeStepWithInput($step, new Input('two'));

    $step->resetAfterRun();

    expect(helper_invokeStepWithInput($step, new Input('three')))->toHaveCount(1);
});

it('converts non array output to array with a certain key using the outputKey() method', function () {
    $step = helper_getValueReturningStep('bar')->outputKey('foo');

    $outputs = helper_invokeStepWithInput($step);

    expect($outputs[0]->get())->toBe(['foo' => 'bar']);
});

it('keeps input data in output when keepInputData() was called', function () {
    $step = helper_getValueReturningStep(['bar' => 'baz'])
        ->keepInputData();

    $output = helper_invokeStepWithInput($step, new Input(['foo' => 'quz']));

    expect($output[0]->get())->toBe(['bar' => 'baz', 'foo' => 'quz']);
});

it('keeps non array input data in array output with key', function () {
    $step = helper_getValueReturningStep(['bar' => 'baz'])
        ->keepInputData('foo');

    $output = helper_invokeStepWithInput($step, new Input('quz'));

    expect($output[0]->get())->toBe(['bar' => 'baz', 'foo' => 'quz']);
});

it('throws an error when non array input should be kept but no key is defined', function () {
    $step = helper_getValueReturningStep(['bar' => 'baz'])
        ->keepInputData();

    helper_invokeStepWithInput($step, new Input('quz'));
})->throws(Exception::class);

it('does not replace output data when a key from input to keep is also defined in output', function () {
    $step = helper_getValueReturningStep(['foo' => 'four', 'bar' => 'five'])
        ->keepInputData('foo');

    $output = helper_invokeStepWithInput($step, new Input(['foo' => 'one', 'bar' => 'two', 'baz' => 'three']));

    expect($output[0]->get())->toBe(['foo' => 'four', 'bar' => 'five', 'baz' => 'three']);
});

it(
    'throws an exception when input should be kept, output is non array value and no output key is defined',
    function () {
        $step = helper_getValueReturningStep('three')
            ->keepInputData();

        helper_invokeStepWithInput($step, new Input(['foo' => 'one', 'bar' => 'two']));
    }
)->throws(Exception::class);

it(
    'works when output is non array value but it\'s converted to an array using the outputKey() method',
    function () {
        $step = helper_getValueReturningStep('three')
            ->keepInputData()
            ->outputKey('baz');

        $outputs = helper_invokeStepWithInput($step, new Input(['foo' => 'one', 'bar' => 'two']));

        expect($outputs[0]->get())->toBe(['baz' => 'three', 'foo' => 'one', 'bar' => 'two']);
    }
);

it('keeps the original input data when useInputKey() is used', function () {
    $step = helper_getValueReturningStep(['baz' => 'three'])
        ->keepInputData()
        ->useInputKey('bar');

    $outputs = helper_invokeStepWithInput($step, ['foo' => 'one', 'bar' => 'two']);

    expect($outputs[0]->get())->toBe(['baz' => 'three', 'foo' => 'one', 'bar' => 'two']);
});

it('applies a Closure refiner to the steps output', function () {
    $step = helper_getValueReturningStep('output');

    $step->refineOutput(function (mixed $outputValue) {
        return $outputValue . ' refined';
    });

    $outputs = helper_invokeStepWithInput($step);

    expect($outputs[0]->get())->toBe('output refined');
});

it('applies an instance of the RefinerInterface to the steps output', function () {
    $step = helper_getInputReturningStep();

    $step->refineOutput(StringRefiner::betweenFirst('foo', 'baz'));

    $outputs = helper_invokeStepWithInput($step, 'foo bar baz');

    expect($outputs[0]->get())->toBe('bar');
});

it('applies multiple refiners to the steps output in the order they\'re added', function () {
    $step = helper_getInputReturningStep();

    $step
        ->refineOutput(StringRefiner::betweenFirst('foo', 'baz'))
        ->refineOutput(function (mixed $outputValue) {
            return $outputValue . ' refined';
        })
        ->refineOutput(function (mixed $outputValue) {
            return $outputValue . ', and refined further';
        });

    $outputs = helper_invokeStepWithInput($step, 'foo bar baz');

    expect($outputs[0]->get())->toBe('bar refined, and refined further');
});

it('applies refiners to certain keys from array output when the key is provided', function () {
    $step = helper_getInputReturningStep();

    $step
        ->refineOutput('foo', StringRefiner::betweenFirst('lorem', 'dolor'))
        ->refineOutput('baz', function (mixed $outputValue) {
            return 'refined ' . $outputValue;
        });

    $outputs = helper_invokeStepWithInput(
        $step,
        ['foo' => 'lorem ipsum dolor', 'bar' => 'bla', 'baz' => 'quz'],
    );

    expect($outputs[0]->get())->toBe([
        'foo' => 'ipsum',
        'bar' => 'bla',
        'baz' => 'refined quz',
    ]);
});

test('you can apply multiple refiners to the same output array key', function () {
    $step = helper_getInputReturningStep();

    $step
        ->refineOutput('foo', StringRefiner::betweenFirst('lorem', 'dolor'))
        ->refineOutput('foo', function (mixed $outputValue) {
            return $outputValue . ' yolo';
        });

    $outputs = helper_invokeStepWithInput(
        $step,
        ['foo' => 'lorem ipsum dolor', 'bar' => 'bla'],
    );

    expect($outputs[0]->get())->toBe([
        'foo' => 'ipsum yolo',
        'bar' => 'bla',
    ]);
});

it(
    'uses the original input value when applying a refiner, not only the value of an input array key chosen via ' .
    'useInputKey()',
    function () {
        $step = helper_getInputReturningStep();

        $step
            ->useInputKey('bar')
            ->refineOutput(function (mixed $outputValue, mixed $originalInputValue) {
                return $originalInputValue;
            });

        $outputs = helper_invokeStepWithInput(
            $step,
            ['foo' => 'one', 'bar' => 'two'],
        );

        expect($outputs[0]->get())->toBe(['foo' => 'one', 'bar' => 'two']);
    }
);

test('you can define aliases for output keys for addToResult()', function () {
    $step = new class () extends Step {
        protected function invoke(mixed $input): Generator
        {
            yield [
                'foo' => 'one',
                'bar' => 'two',
                'baz' => 'three',
            ];
        }

        protected function outputKeyAliases(): array
        {
            return [
                'woo' => 'foo',
                'war' => 'bar',
                'waz' => 'baz',
            ];
        }
    };

    $step->addToResult(['woo', 'far' => 'war', 'waz']);

    $outputs = helper_invokeStepWithInput($step);

    expect($outputs[0]->result)->toBeInstanceOf(Result::class);

    expect($outputs[0]->result?->toArray())->toBe([
        'woo' => 'one',
        'far' => 'two',
        'waz' => 'three',
    ]);
});

test('you can filter outputs using an output key alias', function () {
    $step = new class () extends Step {
        protected function invoke(mixed $input): Generator
        {
            yield [
                'foo' => 'one',
                'bar' => 'two',
            ];
        }

        protected function outputKeyAliases(): array
        {
            return [
                'baz' => 'bar',
            ];
        }
    };

    $step->where('baz', Filter::equal('two'));

    $outputs = helper_invokeStepWithInput($step);

    expect($outputs[0])->toBeInstanceOf(Output::class);
});

it('can filter by a key that only exists in the serialized version of an output object', function () {
    $step = new class () extends Step {
        protected function invoke(mixed $input): Generator
        {
            yield new class () {
                public string $foo = 'one';

                public string $bar = 'two';

                /**
                 * @return string[]
                 */
                public function __serialize(): array
                {
                    return [
                        'foo' => $this->foo,
                        'bar' => $this->bar,
                        'baz' => $this->bar,
                    ];
                }
            };
        }

        protected function outputKeyAliases(): array
        {
            return [
                'quz' => 'baz',
            ];
        }
    };

    $step->where('quz', Filter::equal('two'));

    $outputs = helper_invokeStepWithInput($step);

    expect($outputs[0])->toBeInstanceOf(Output::class);
});
