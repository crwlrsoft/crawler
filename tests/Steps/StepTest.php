<?php

namespace tests\Steps;

use Crwlr\Crawler\Input;
use Crwlr\Crawler\Loader\Http\Messages\RespondedRequest;
use Crwlr\Crawler\Logger\CliLogger;
use Crwlr\Crawler\Output;
use Crwlr\Crawler\Steps\Filters\Filter;
use Crwlr\Crawler\Steps\Loading\Http;
use Crwlr\Crawler\Steps\Refiners\StringRefiner;
use Crwlr\Crawler\Steps\Step;
use Crwlr\Crawler\Steps\StepOutputType;
use Exception;
use Generator;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use stdClass;
use tests\_Stubs\DummyLogger;

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
    $step = new class extends Step {
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

test('The invokeStep method wraps the values returned by invoke in Output objects', function () {
    $step = helper_getValueReturningStep('returnValue');

    $output = helper_invokeStepWithInput($step);

    expect($output)->toHaveCount(1)
        ->and($output[0])->toBeInstanceOf(Output::class)
        ->and($output[0]->get())->toBe('returnValue');
});

test('keep() can pick keys from nested (array) output using dot notation', function () {
    $step = helper_getValueReturningStep([
        'users' => [
            ['user' => 'otsch', 'firstname' => 'Christian', 'surname' => 'Olear'],
            ['user' => 'juerx', 'firstname' => 'Jürgen', 'surname' => 'Müller'],
            ['user' => 'sandy', 'firstname' => 'Sandra', 'surname' => 'Mayr'],
        ],
        'foo' => 'bar',
    ])
        ->keep(['nickname' => 'users.0.user', 'foo']);

    $output = helper_invokeStepWithInput($step);

    expect($output[0]->keep)->toBe(['nickname' => 'otsch', 'foo' => 'bar']);
});

test('keep() picks keys from nested output including a RespondedRequest object', function () {
    $step = helper_getValueReturningStep([
        'response' => new RespondedRequest(
            new Request('GET', 'https://www.example.com/something'),
            new Response(200, body: 'Hi :)'),
        ),
        'foo' => 'bar',
    ])
        ->keep(['content' => 'response.body']);

    $output = helper_invokeStepWithInput($step);

    expect($output[0]->keep)->toBe(['content' => 'Hi :)']);
});

it('maps output keys to different keys when defined in the array passed to keep()', function () {
    $step = helper_getValueReturningStep(['user' => 'otsch', 'firstname' => 'Christian', 'surname' => 'Olear'])
        ->keep(['foo' => 'firstname', 'bar' => 'surname']);

    $output = helper_invokeStepWithInput($step);

    expect($output[0]->keep)->toBe(['foo' => 'Christian', 'bar' => 'Olear']);
});

it('uses a key from array input when defined', function () {
    $step = helper_getInputReturningStep()->useInputKey('bar');

    $output = helper_invokeStepWithInput($step, new Input(
        ['foo' => 'fooValue', 'bar' => 'barValue', 'baz' => 'bazValue'],
    ));

    expect($output)->toHaveCount(1)
        ->and($output[0]->get())->toBe('barValue');
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
                'Can\'t get key from input, because input is of type ' . gettype($inputValue) . ' instead of array.',
            );
    },
)->with([
    ['string'],
    [0],
    [new stdClass()],
]);

it('does not lose previously kept data, when it uses the useInputKey() method', function () {
    $step = helper_getValueReturningStep(['test' => 'test'])->useInputKey('foo');

    $outputs = helper_invokeStepWithInput($step, new Input(['foo' => 'test'], ['some' => 'thing']));

    expect($outputs[0]->keep)->toBe(['some' => 'thing']);
});

it(
    'also passes on kept data through further steps when they don\'t define any further data to keep',
    function () {
        $step = helper_getValueReturningStep('returnValue');

        $output = helper_invokeStepWithInput($step, new Input('inputValue', ['prevProperty' => 'foobar']));

        expect($output)->toHaveCount(1)
            ->and($output[0]->keep)->toBe(['prevProperty' => 'foobar']);
    },
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
    },
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
    },
);

it('makes outputs unique when uniqueOutput was called', function () {
    $step = helper_getStepYieldingMultipleNumbers();

    $step->uniqueOutputs();

    $output = helper_invokeStepWithInput($step, new Input('anything'));

    expect($output)->toHaveCount(5)
        ->and($output[0]->get())->toBe('one')
        ->and($output[1]->get())->toBe('two')
        ->and($output[2]->get())->toBe('three')
        ->and($output[3]->get())->toBe('four')
        ->and($output[4]->get())->toBe('five');
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
    $step = new class extends Step {
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
        $step = new class extends Step {
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
    },
);

test(
    'when calling validateAndSanitizeStringOrStringable() and the input is array with multiple elements it logs ' .
    'an error message',
    function () {
        $logger = new DummyLogger();

        $step = new class extends Step {
            protected function validateAndSanitizeInput(mixed $input): string
            {
                return $this->validateAndSanitizeStringOrStringable($input);
            }

            protected function invoke(mixed $input): Generator
            {
                yield $input;
            }
        };

        $step->addLogger($logger);

        helper_invokeStepWithInput($step, ['inputValue', 'foo' => 'bar']);

        expect($logger->messages)->not->toBeEmpty()
            ->and($logger->messages[0]['message'])->toStartWith(
                'A step was called with input that it can not work with:',
            )
            ->and($logger->messages[0]['message'])->toEndWith('. The invalid input is of type array.');
    },
);

test(
    'when throwing an InvalidArgumentException from the validateAndSanitizeInput() it is caught and logged as an error',
    function () {
        $logger = new DummyLogger();

        $step = new class extends Step {
            protected function validateAndSanitizeInput(mixed $input): string
            {
                throw new InvalidArgumentException('hey :)');
            }

            protected function invoke(mixed $input): Generator
            {
                yield $input;
            }
        };

        $step->addLogger($logger);

        $outputs = helper_invokeStepWithInput($step, 'anything');

        expect($outputs)->toBeEmpty()
            ->and($logger->messages)->not->toBeEmpty()
            ->and($logger->messages[0]['message'])->toBe(
                'A step was called with input that it can not work with: hey :)',
            );
    },
);

test(
    'when throwing an Exception that is not an InvalidArgumentException, from the validateAndSanitizeInput() it is ' .
    'not caught',
    function () {
        $logger = new DummyLogger();

        $step = new class extends Step {
            protected function validateAndSanitizeInput(mixed $input): string
            {
                throw new Exception('hey :)');
            }

            protected function invoke(mixed $input): Generator
            {
                yield $input;
            }
        };

        $step->addLogger($logger);

        helper_invokeStepWithInput($step, 'anything');
    },
)->throws(Exception::class);

it('is possible that a step does not produce any output at all', function () {
    $step = new class extends Step {
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

    expect($output)->toHaveCount(1)
        ->and($output[0]->get())->toBe('bar');
});

test('You can add and call an updateInputUsingOutput callback', function () {
    $step = helper_getValueReturningStep('something');

    $step->updateInputUsingOutput(function (mixed $input, mixed $output) {
        return $input . ' ' . $output;
    });

    $updatedInput = $step->callUpdateInputUsingOutput(new Input('Boo'), new Output('Yah!'));

    expect($updatedInput)->toBeInstanceOf(Input::class)
        ->and($updatedInput->get())->toBe('Boo Yah!');
});

it('does not lose previously kept data, when updateInputUsingOutput() is called', function () {
    $step = helper_getValueReturningStep('something');

    $step->updateInputUsingOutput(function (mixed $input, mixed $output) {
        return $input . ' ' . $output;
    });

    $updatedInput = $step->callUpdateInputUsingOutput(
        new Input('Some', ['foo' => 'bar']),
        new Output('thing'),
    );

    expect($updatedInput->keep)->toBe(['foo' => 'bar']);
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
        $step = new class extends Step {
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
    },
);

test('When a step has max outputs defined, it won\'t call the invoke method after the limit was reached', function () {
    $step = new class extends Step {
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

test('keeping a scalar output value with keep() also works when outputKey() was used', function () {
    $step = new class extends Step {
        protected function invoke(mixed $input): Generator
        {
            yield 'hey';
        }

        public function outputType(): StepOutputType
        {
            return StepOutputType::Scalar;
        }
    };

    $step
        ->outputKey('greeting')
        ->keep();

    $step->validateBeforeRun(Http::get());

    $outputs = helper_invokeStepWithInput($step, 'guten tag');

    expect($outputs[0]->get())->toBe(['greeting' => 'hey']);
});

it('keeps the original input data when useInputKey() is used', function () {
    $step = helper_getValueReturningStep(['baz' => 'three'])
        ->keepFromInput()
        ->useInputKey('bar');

    $outputs = helper_invokeStepWithInput($step, ['foo' => 'one', 'bar' => 'two']);

    expect($outputs[0]->get())->toBe(['baz' => 'three'])
        ->and($outputs[0]->keep)->toBe(['foo' => 'one', 'bar' => 'two']);
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
    },
);

it('useInputKey() can be used to get data that was kept from a previous step with keep() or keepAs()', function () {
    $step = helper_getInputReturningStep();

    $step->useInputKey('bar');

    $outputs = helper_invokeStepWithInput($step, new Input('value', keep: ['bar' => 'baz']));

    expect($outputs[0]->get())->toBe('baz');
});

test('you can define aliases for output keys and they are considered when using keep()', function () {
    $step = new class extends Step {
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

    $step->keep(['woo', 'far' => 'war', 'waz']);

    $outputs = helper_invokeStepWithInput($step);

    expect($outputs[0]->keep)->toBe([
        'woo' => 'one',
        'far' => 'two',
        'waz' => 'three',
    ]);
});

test('you can filter outputs using an output key alias', function () {
    $step = new class extends Step {
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
    $step = new class extends Step {
        protected function invoke(mixed $input): Generator
        {
            yield new class {
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
