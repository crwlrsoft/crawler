<?php

namespace tests\Steps;

use Crwlr\Crawler\Input;
use Crwlr\Crawler\Logger\CliLogger;
use Crwlr\Crawler\Output;
use Crwlr\Crawler\Result;
use Crwlr\Crawler\Steps\Step;
use Exception;
use Generator;
use PHPUnit\Framework\TestCase;

use function tests\helper_getInputReturningStep;
use function tests\helper_getStdClassWithData;
use function tests\helper_getStepYieldingArrayWithNumber;
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

    $output = $this->getActualOutput();

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
            ->setResultKey('property');

        $output = helper_invokeStepWithInput($step);

        expect($output[0]->result)->toBeInstanceOf(Result::class);

        expect($output[0]->result->toArray())->toBe(['property' => 'returnValue']); // @phpstan-ignore-line
    }
);

it('creates a Result object with the data from yielded array when addKeysToResult is used', function () {
    $step = helper_getValueReturningStep(['foo' => 'bar', 'baz' => 'yo'])
        ->addKeysToResult();

    $output = helper_invokeStepWithInput($step);

    expect($output[0]->result)->toBeInstanceOf(Result::class);

    expect($output[0]->result->toArray())->toBe(['foo' => 'bar', 'baz' => 'yo']); // @phpstan-ignore-line
});

it('picks keys from the output array when you pass an array of keys to addKeysToResult', function () {
    $step = helper_getValueReturningStep(['user' => 'otsch', 'firstname' => 'Christian', 'surname' => 'Olear'])
        ->addKeysToResult(['firstname', 'surname']);

    $output = helper_invokeStepWithInput($step);

    expect($output[0]->result)->toBeInstanceOf(Result::class);

    expect($output[0]->result->toArray())->toBe(['firstname' => 'Christian', 'surname' => 'Olear']); // @phpstan-ignore-line
});

it('maps output keys to different result keys when defined in the array passed to addKeysToResult', function () {
    $step = helper_getValueReturningStep(['user' => 'otsch', 'firstname' => 'Christian', 'surname' => 'Olear'])
        ->addKeysToResult(['foo' => 'firstname', 'bar' => 'surname']);

    $output = helper_invokeStepWithInput($step);

    expect($output[0]->result)->toBeInstanceOf(Result::class);

    expect($output[0]->result->toArray())->toBe(['foo' => 'Christian', 'bar' => 'Olear']); // @phpstan-ignore-line
});

test('The addsToOrCreatesResult method returns true when a result key is set', function () {
    $step = helper_getValueReturningStep('test')->setResultKey('test');

    expect($step->addsToOrCreatesResult())->toBeTrue();
});

test('The addsToOrCreatesResult method returns true when it adds keys to result', function () {
    $step = helper_getValueReturningStep(['test' => 'yo'])->addKeysToResult();

    expect($step->addsToOrCreatesResult())->toBeTrue();
});

test('The addsToOrCreatesResult method returns false when no result key is set and it doesn\'t add keys', function () {
    $step = helper_getValueReturningStep('lol');

    expect($step->addsToOrCreatesResult())->toBeFalse();
});

it('uses a key from array input when defined', function () {
    $step = helper_getInputReturningStep()->useInputKey('bar');

    $output = helper_invokeStepWithInput($step, new Input(
        ['foo' => 'fooValue', 'bar' => 'barValue', 'baz' => 'bazValue']
    ));

    expect($output)->toHaveCount(1);

    expect($output[0]->get())->toBe('barValue');
});

it('doesn\'t add the result object to the Input object only to the Output', function () {
    $step = helper_getValueReturningStep('Stand with Ukraine!')
        ->setResultKey('property');

    $input = new Input('inputValue');

    $output = helper_invokeStepWithInput($step);

    expect($output[0]->result)->toBeInstanceOf(Result::class);

    expect($input->result)->toBe(null);
});

it('appends properties to a result object that was already included with the Input object', function () {
    $step = helper_getValueReturningStep('returnValue')
        ->setResultKey('property');

    $prevResult = new Result();

    $prevResult->set('prevProperty', 'foobar');

    $input = new Input('inputValue', $prevResult);

    $output = helper_invokeStepWithInput($step, $input);

    expect($output[0]->result)->toBeInstanceOf(Result::class);

    expect($output[0]->result->toArray())->toBe([ // @phpstan-ignore-line
        'prevProperty' => 'foobar',
        'property' => 'returnValue',
    ]);
});

it(
    'also passes on Result objects through further steps when they don\'t define further result resource properties',
    function () {
        $step = helper_getValueReturningStep('returnValue');

        $prevResult = new Result();

        $prevResult->set('prevProperty', 'foobar');

        $output = helper_invokeStepWithInput($step, new Input('inputValue', $prevResult));

        expect($output[0]->result)->toBeInstanceOf(Result::class);

        expect($output[0]->result->toArray())->toBe(['prevProperty' => 'foobar']); // @phpstan-ignore-line
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

it('still returns output from invokeStep when dontCascade was called', function () {
    // Explanation: the Crawler (and Group) class has to take care of not cascading the output to the next step.
    // But it still needs the output of a step that shouldn't cascade in some cases.
    $step = helper_getValueReturningStep('something');

    $output = helper_invokeStepWithInput($step);

    expect($output)->toHaveCount(1);

    $step->dontCascade();

    $output = helper_invokeStepWithInput($step);

    expect($output)->toHaveCount(1);
});

it('tells you if its output shall be cascaded to the next step', function () {
    $step = helper_getInputReturningStep();

    expect($step->cascades())->toBeTrue();

    $step->dontCascade();

    expect($step->cascades())->toBeFalse();
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

it('keeps array input data when output is non array and output key is defined', function () {
    $step = helper_getValueReturningStep('three')
        ->keepInputData(outputKey: 'baz');

    $output = helper_invokeStepWithInput($step, new Input(['foo' => 'one', 'bar' => 'two']));

    expect($output[0]->get())->toBe(['baz' => 'three', 'foo' => 'one', 'bar' => 'two']);
});

it(
    'throws an exception when input should be kept, output is non array value and no output key is defined',
    function () {
        $step = helper_getValueReturningStep('three')
            ->keepInputData();

        helper_invokeStepWithInput($step, new Input(['foo' => 'one', 'bar' => 'two']));
    }
)->throws(Exception::class);
