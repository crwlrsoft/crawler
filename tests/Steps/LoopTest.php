<?php

namespace tests\Steps;

use Crwlr\Crawler\Input;
use Crwlr\Crawler\Loader\Http\HttpLoader;
use Crwlr\Crawler\Logger\CliLogger;
use Crwlr\Crawler\Output;
use Crwlr\Crawler\Steps\Loading\LoadingStepInterface;
use Crwlr\Crawler\Steps\Loop;
use Crwlr\Crawler\Steps\Step;
use Crwlr\Crawler\Steps\StepInterface;
use Crwlr\Crawler\UserAgents\BotUserAgent;
use Exception;
use Generator;
use Mockery;
use function tests\helper_arrayToGenerator;
use function tests\helper_getInputReturningStep;
use function tests\helper_getNumberIncrementingStep;
use function tests\helper_getStdClassWithData;
use function tests\helper_getValueReturningStep;
use function tests\helper_invokeStepWithInput;
use function tests\helper_traverseIterable;

test(
    'It wraps a normal Step and repeats invoking it with it\'s own output until there is no more output',
    function () {
        $step = new class () extends Step {
            public int $_callCount = 0;

            protected function invoke(mixed $input): Generator
            {
                if ($this->_callCount === 0) {
                    expect($input)->toBe('foo');
                } else {
                    expect($input)->toBe($this->_callCount);
                }

                $this->_callCount++;

                if ($this->_callCount < 5) {
                    yield $this->_callCount;
                }
            }
        };

        $loopStep = new Loop($step);

        helper_traverseIterable($loopStep->invokeStep(new Input('foo')));

        expect($step->_callCount)->toBe(5);
    }
);

test(
    'To avoid infinite loops it has a max iterations limit, that by default is 1000',
    function ($repetitions, $stopAt) {
        $step = new class ($repetitions) extends Step {
            public int $_callCount = 0;

            public function __construct(private int $yieldUntilRepetition)
            {
            }

            protected function invoke(mixed $input): Generator
            {
                $this->_callCount++;

                if ($this->_callCount <= $this->yieldUntilRepetition) {
                    yield $this->_callCount;
                }
            }
        };

        $loopStep = new Loop($step);

        helper_traverseIterable($loopStep->invokeStep(new Input('foo')));

        expect($step->_callCount)->toBe($stopAt);
    }
)->with([
    [998, 999],     // callcount is 1 more than the number of repetitions we defined it should yield,
    [999, 1000],    // because the last time it yields something it's called once again with that output.
    [1000, 1000],
    [1001, 1000],
    [1100, 1000],
]);

test('You can set your own max iteration limit', function ($customLimit) {
    $step = new class () extends Step {
        public int $_callCount = 0;

        protected function invoke(mixed $input): Generator
        {
            $this->_callCount++;

            yield $this->_callCount;
        }
    };

    $loopStep = (new Loop($step))->maxIterations($customLimit);

    helper_traverseIterable($loopStep->invokeStep(new Input('foo')));

    expect($step->_callCount)->toBe($customLimit);
})->with([10, 100, 100000]);

test('You can use a Closure to transform an iterations output to the input for the next step', function () {
    $loopStep = new Loop(new class () extends Step {
        protected function invoke(mixed $input): Generator
        {
            expect($input)->toBeInt();

            yield 'output ' . ($input + 1);
        }
    });

    $loopStep->withInput(function (mixed $input, mixed $output) {
        $outputValue = $output;

        return (int) substr($outputValue, -1, 1);
    });

    $loopStep->maxIterations(5);

    $outputs = helper_invokeStepWithInput($loopStep, 0);

    expect($outputs[0]->get())->toBe('output 1');

    expect($outputs[1]->get())->toBe('output 2');

    expect($outputs[2]->get())->toBe('output 3');

    expect($outputs[3]->get())->toBe('output 4');

    expect($outputs[4]->get())->toBe('output 5');
});

test('You can use a Step to make the input for the next iteration from the output', function () {
    $loopStep = new Loop(new class () extends Step {
        protected function invoke(mixed $input): Generator
        {
            expect($input)->toBeInt();

            yield 'foo ' . ($input + 1);
        }
    });

    $loopStep->withInput(new class () extends Step {
        protected function invoke(mixed $input): Generator
        {
            yield (int) substr($input, -1, 1);
        }
    });

    $loopStep->maxIterations(5);

    $outputs = helper_invokeStepWithInput($loopStep, 0);

    expect($outputs[0]->get())->toBe('foo 1');

    expect($outputs[1]->get())->toBe('foo 2');

    expect($outputs[2]->get())->toBe('foo 3');

    expect($outputs[3]->get())->toBe('foo 4');

    expect($outputs[4]->get())->toBe('foo 5');
});

test('When the step has output but the withInput Closure returns null it stops looping', function () {
    $loopStep = new Loop(new class () extends Step {
        protected function invoke(mixed $input): Generator
        {
            expect($input)->toBeInt();

            yield $input + 1;
        }
    });

    $loopStep->withInput(function (mixed $input, mixed $output) {
        return $output < 2 ? $output : null;
    });

    $loopStep->maxIterations(5);

    $outputs = helper_invokeStepWithInput($loopStep, 0);

    expect($outputs)->toHaveCount(2);

    expect($outputs[0]->get())->toBe(1);

    expect($outputs[1]->get())->toBe(2);
});

test('When the step has output but the withInput Step has no output it stops looping', function () {
    $loopStep = new Loop(new class () extends Step {
        protected function invoke(mixed $input): Generator
        {
            expect($input)->toBeInt();

            yield $input + 1;
        }
    });

    $loopStep->withInput(new class () extends Step {
        protected function invoke(mixed $input): Generator
        {
            if ($input < 2) {
                yield $input;
            }
        }
    });

    $loopStep->maxIterations(5);

    $outputs = helper_invokeStepWithInput($loopStep, 0);

    expect($outputs)->toHaveCount(2);

    expect($outputs[0]->get())->toBe(1);

    expect($outputs[1]->get())->toBe(2);
});

test('You can set a logger and it\'s passed on to the wrapped step that is looped', function () {
    $step = Mockery::mock(StepInterface::class);

    $step->shouldReceive('addLogger')->once();

    $loopStep = new Loop($step);

    $loopStep->addLogger(new CliLogger());
});

test('You can set a loader and it\'s passed on to the wrapped step that is looped', function () {
    $step = Mockery::mock(LoadingStepInterface::class);

    $step->shouldReceive('addLoader')->once();

    $loopStep = new Loop($step);

    $loopStep->addLoader(new HttpLoader(new BotUserAgent('FooBot')));
});

test(
    'When the step yields multiple outputs, it outputs all and loops with the last output of each iteration',
    function () {
        $step = Mockery::mock(StepInterface::class);

        // Initial call returning 3 outputs
        $step->shouldReceive('cascades')->andReturn(true);

        $step->shouldReceive('invokeStep')->once()->andReturn(
            helper_arrayToGenerator([new Output('foo'), new Output('bar'), new Output('baz')])
        );

        // Looping call with last output of first invoke call
        $step->shouldReceive('invokeStep')->once()->withArgs(function (Input $input) {
            return $input->get() === 'baz';
        })->andReturn(helper_arrayToGenerator([new Output('Lorem'), new Output('Ipsum')]));

        // And another call with the last output of the second iteration
        $step->shouldReceive('invokeStep')->once()->withArgs(function (Input $input) {
            return $input->get() === 'Ipsum';
        })->andReturn(helper_arrayToGenerator([]));

        $loopStep = new Loop($step);

        $outputs = helper_invokeStepWithInput($loopStep, 'test');

        expect($outputs[0]->get())->toBe('foo');

        expect($outputs[1]->get())->toBe('bar');

        expect($outputs[2]->get())->toBe('baz');

        expect($outputs[3]->get())->toBe('Lorem');

        expect($outputs[4]->get())->toBe('Ipsum');
    }
);

test('It doesn\'t output anything when the dontCascade method was called', function () {
    $step = helper_getValueReturningStep('something');

    $loopStep = (new Loop($step))->maxIterations(10);

    expect(helper_invokeStepWithInput($loopStep, 'foo'))->toHaveCount(10);

    $loopStep->dontCascade();

    expect(helper_invokeStepWithInput($loopStep, 'foo'))->toHaveCount(0);
});

test('It immediately cascades outputs to the next step', function () {
    $step = new class () extends Step {
        public int $_iterationCount = 0;

        protected function invoke(mixed $input): Generator
        {
            $this->_iterationCount++;

            yield 'love';
        }
    };

    $loopStep = (new Loop($step))->maxIterations(10);

    $anyOutputAtAll = false;

    $i = 1;

    foreach ($loopStep->invokeStep(new Input('peace')) as $output) {
        $anyOutputAtAll = true;

        expect($step->_iterationCount)->toBe($i);

        $i++;
    }

    expect($anyOutputAtAll)->toBeTrue();
});

test(
    'It only cascades outputs to the next step after it finished looping when cascadeWhenFinished is called',
    function () {
        $step = new class () extends Step {
            public int $_iterationCount = 0;

            protected function invoke(mixed $input): Generator
            {
                $this->_iterationCount++;

                yield 'happiness';
            }
        };

        $loopStep = (new Loop($step))->maxIterations(10);

        $loopStep->cascadeWhenFinished();

        foreach ($loopStep->invokeStep(new Input('pew')) as $output) {
            expect($step->_iterationCount)->toBe(10);
        }
    }
);

test('It resets deferred outputs when they are yielded', function () {
    $loopStep = (new Loop(helper_getValueReturningStep('pew')))->maxIterations(10);

    $loopStep->cascadeWhenFinished();

    expect(helper_invokeStepWithInput($loopStep, 'pew'))->toHaveCount(10);

    // If it wouldn't reset the previous deferred outputs it would now be 20 outputs
    expect(helper_invokeStepWithInput($loopStep, 'pew'))->toHaveCount(10);
});

test('You can add and call an updateInputUsingOutput callback', function () {
    $step = (new Loop(helper_getValueReturningStep(1)))
        ->updateInputUsingOutput(function (mixed $input, mixed $output) {
            return $input . ' ' . $output;
        });

    $updatedInput = $step->callUpdateInputUsingOutput(new Input('Boo'), new Output('Yah!'));

    expect($updatedInput)->toBeInstanceOf(Input::class);

    expect($updatedInput->get())->toBe('Boo Yah!');
});

test('It loops reusing the same input that can be updated via a callback when withInput is used', function () {
    $step = new Loop(new class () extends Step {
        protected function invoke(mixed $input): Generator
        {
            yield array_pop($input) + (array_pop($input) ?? 0);
        }
    });

    $step->withInput(function (mixed $input, mixed $output) {
        $input[] = $output;

        return $input;
    });

    $step->maxIterations(10);

    $outputs = helper_invokeStepWithInput($step, [1]);

    expect($outputs[0]->get())->toBe(1);

    expect($outputs[1]->get())->toBe(2);

    expect($outputs[2]->get())->toBe(3);

    expect($outputs[3]->get())->toBe(5);

    expect($outputs[4]->get())->toBe(8);

    expect($outputs[5]->get())->toBe(13);

    expect($outputs[6]->get())->toBe(21);

    expect($outputs[7]->get())->toBe(34);

    expect($outputs[8]->get())->toBe(55);

    expect($outputs[9]->get())->toBe(89);
});

test('It stops looping when the withInput callback returns null', function () {
    $step = (new Loop(helper_getInputReturningStep()))
        ->withInput(function (mixed $input, mixed $output) {
            return $input < 5 ? $input + 1 : null;
        })
        ->maxIterations(10);

    $outputs = helper_invokeStepWithInput($step, 1);

    expect($outputs)->toHaveCount(5);

    expect($outputs[0]->get())->toBe(1);

    expect($outputs[1]->get())->toBe(2);

    expect($outputs[2]->get())->toBe(3);

    expect($outputs[3]->get())->toBe(4);

    expect($outputs[4]->get())->toBe(5);
});

test(
    'It calls the withInput method when there is no output but the callWithoutOutput param is set to true',
    function () {
        $step = new class () extends Step {
            public int $_callcount = 0;

            protected function invoke(mixed $input): Generator
            {
                $this->_callcount++;

                if ($input === true) {
                    yield 'it';
                }
            }
        };

        $firstCall = true;

        $loopStep = (new Loop($step))
            ->maxIterations(5)
            ->withInput(function (mixed $input, mixed $output) use (& $firstCall) {
                expect($output)->toBeNull();

                if ($firstCall === true) {
                    $firstCall = false;

                    return $input;
                }

                return null;
            }, true);

        helper_traverseIterable($loopStep->invokeStep(new Input('yo')));

        expect($step->_callcount)->toBe(2);
    }
);

test('It also calls the withInput method without output when keepLoopingWithoutOutput is called', function () {
    $step = new class () extends Step {
        public int $_callcount = 0;

        protected function invoke(mixed $input): Generator
        {
            $this->_callcount++;

            if ($input === 'yield output') {
                yield 'ok';
            }
        }
    };

    $firstCall = true;

    $loopStep = (new Loop($step))
        ->maxIterations(10)
        ->withInput(function (mixed $input, mixed $output) use (& $firstCall) {
            expect($output)->toBeNull();

            if ($firstCall === true) {
                $firstCall = false;

                return $input;
            }

            return null;
        })
        ->keepLoopingWithoutOutput();

    helper_traverseIterable($loopStep->invokeStep(new Input('don\'t yield output')));

    expect($step->_callcount)->toBe(2);
});

test('It calls the withInput callback only once when callWithInputOnlyOnce was called', function () {
    $step = new class () extends Step {
        public bool $firstCall = true;

        protected function invoke(mixed $input): Generator
        {
            if ($this->firstCall) {
                foreach (['one', 'two', 'three'] as $value) {
                    yield $value;
                }

                $this->firstCall = false;
            }
        }
    };

    $callbackCallCount = 0;

    $loopStep = (new Loop($step))
        ->maxIterations(10)
        ->withInput(function (mixed $input, mixed $output) use (& $callbackCallCount) {
            if ($output === null) {
                throw new Exception('Expect real output');
            }

            expect($output)->toBe('three');

            $callbackCallCount++;

            return null;
        })
        ->callWithInputOnlyOnce();

    helper_traverseIterable($loopStep->invokeStep(new Input('go')));

    expect($callbackCallCount)->toBe(1);
});

test(
    'It stops when the callback passed to the stopIf method returns true and it stops before yielding the output of ' .
    'that iteration',
    function () {
        $step = new Loop(new class () extends Step {
            protected function invoke(mixed $input): Generator
            {
                yield $input + 1;
            }
        });

        $step->maxIterations(10);

        $step->stopIf(function (mixed $input, mixed $output) {
            return $output > 3;
        });

        $outputs = helper_invokeStepWithInput($step, 0);

        expect($outputs)->toHaveCount(3);

        expect($outputs[0]->get())->toBe(1);

        expect($outputs[1]->get())->toBe(2);

        expect($outputs[2]->get())->toBe(3);
    }
);

it('adds Results to the Outputs when you set a result key', function () {
    $step = (new Loop(helper_getValueReturningStep('test')))
        ->maxIterations(2)
        ->setResultKey('resultKey');

    $outputs = helper_invokeStepWithInput($step, 'test');

    expect($outputs)->toHaveCount(2);

    // I think doing this doesn't make much sense, as the Loop will always add the output to the same Result
    // object, which is why it's an array ['test', 'test'] below.
    expect($outputs[0]->result->get('resultKey'))->toBe(['test', 'test']); // @phpstan-ignore-line

    expect($outputs[1]->result->get('resultKey'))->toBe(['test', 'test']); // @phpstan-ignore-line
});

it('adds Results to the Outputs when you call addKeysToResult', function () {
    $step = (new Loop(helper_getValueReturningStep(['foo' => 'bar', 'yo' => 'lo'])))
        ->maxIterations(2)
        ->addKeysToResult();

    $outputs = helper_invokeStepWithInput($step, 'test');

    expect($outputs)->toHaveCount(2);

    $expectedResultArray = ['foo' => ['bar', 'bar'], 'yo' => ['lo', 'lo']];

    expect($outputs[0]->result->toArray())->toBe($expectedResultArray); // @phpstan-ignore-line

    expect($outputs[1]->result->toArray())->toBe($expectedResultArray); // @phpstan-ignore-line
});

it('only returns unique output when uniqueOutput was called', function () {
    $loop = new Loop(helper_getNumberIncrementingStep());

    $loop->withInput(function (mixed $input, mixed $output) {
        if ($output > 5) {
            return 1;
        }

        return $output;
    });

    $loop->maxIterations(10)
        ->uniqueOutputs();

    $outputs = helper_invokeStepWithInput($loop, new Input(1));

    expect($outputs)->toHaveCount(5);
});

function helper_getStepYieldingArrayWithIncrementingNumber(): Step
{
    return new class () extends Step {
        private int $_number = 1;

        public function _resetNumber(): void
        {
            $this->_number = 1;
        }

        protected function invoke(mixed $input): Generator
        {
            yield [
                'number' => $this->_number,
                'foo' => 'bar' . ($input['addSecondNumber'] === true ? ' ' . $input['secondNumber'] : ''),
            ];

            $this->_number++;
        }
    };
}

function helper_getStepYieldingObjectWithIncrementingNumber(): Step
{
    return new class () extends Step {
        private int $_number = 1;

        public function _resetNumber(): void
        {
            $this->_number = 1;
        }

        protected function invoke(mixed $input): Generator
        {
            yield helper_getStdClassWithData([
                'number' => $this->_number,
                'foo' => 'bar' . ($input->addSecondNumber === true ? ' ' . $input->secondNumber : ''),
            ]);

            $this->_number++;
        }
    };
}

it(
    'only returns unique output when outputs are arrays, when uniqueOutput was called with a key from those arrays',
    function () {
        $step = helper_getStepYieldingArrayWithIncrementingNumber();

        $loop = new Loop($step);

        $loop->withInput(function (mixed $input, mixed $output) {
            if ($output['number'] >= 5) {
                $this->_resetNumber(); // @phpstan-ignore-line
            }

            if (isset($input['secondNumber'])) {
                $input['secondNumber'] += 1;
            }

            return $input;
        });

        $loop->maxIterations(10)
            ->uniqueOutputs();

        $outputs = helper_invokeStepWithInput($loop, new Input(['addSecondNumber' => true, 'secondNumber' => 1]));

        expect($outputs)->toHaveCount(10);

        $step->_resetNumber(); // @phpstan-ignore-line

        $loop->uniqueOutputs('number');

        $outputs = helper_invokeStepWithInput($loop, new Input(['addSecondNumber' => true, 'secondNumber' => 1]));

        expect($outputs)->toHaveCount(5);
    }
);

it(
    'only returns unique output when outputs are objects, when uniqueOutput was called with a property from those ' .
        'objects',
    function () {
        $step = helper_getStepYieldingObjectWithIncrementingNumber();

        $loop = new Loop($step);

        $loop->withInput(function (mixed $input, mixed $output) {
            if ($output->number >= 5) {
                $this->_resetNumber(); // @phpstan-ignore-line
            }

            if (isset($input->secondNumber)) {
                $input->secondNumber += 1;
            }

            return $input;
        });

        $loop->maxIterations(10)
            ->uniqueOutputs();

        $inputObject = helper_getStdClassWithData(['addSecondNumber' => true, 'secondNumber' => 1]);

        $outputs = helper_invokeStepWithInput($loop, new Input($inputObject));

        expect($outputs)->toHaveCount(10);

        $step->_resetNumber(); // @phpstan-ignore-line

        $loop->uniqueOutputs('number');

        $inputObject = helper_getStdClassWithData(['addSecondNumber' => true, 'secondNumber' => 1]);

        $outputs = helper_invokeStepWithInput($loop, new Input($inputObject));

        expect($outputs)->toHaveCount(5);
    }
);
