<?php

namespace tests\Steps;

use Crwlr\Crawler\Input;
use Crwlr\Crawler\Loader\HttpLoader;
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
use function tests\helper_generatorToArray;
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
    $loopStep = new Loop($step);
    $loopStep->maxIterations($customLimit);
    helper_traverseIterable($loopStep->invokeStep(new Input('foo')));
    expect($step->_callCount)->toBe($customLimit);
})->with([10, 100, 100000]);

test('You can use a Closure to transform an iterations output to the input for the next step', function () {
    $step = new class () extends Step {
        protected function invoke(mixed $input): Generator
        {
            expect($input)->toBeInt();

            yield 'output ' . ($input + 1);
        }
    };
    $loopStep = new Loop($step);
    $loopStep->withInput(function (mixed $input, mixed $output) {
        $outputValue = $output;

        return (int) substr($outputValue, -1, 1);
    });
    $loopStep->maxIterations(5);
    $result = helper_generatorToArray($loopStep->invokeStep(new Input(0)));
    expect($result[0]->get())->toBe('output 1');
    expect($result[1]->get())->toBe('output 2');
    expect($result[2]->get())->toBe('output 3');
    expect($result[3]->get())->toBe('output 4');
    expect($result[4]->get())->toBe('output 5');
});

test('You can use a Step to make the input for the next iteration from the output', function () {
    $step = new class () extends Step {
        protected function invoke(mixed $input): Generator
        {
            expect($input)->toBeInt();

            yield 'foo ' . ($input + 1);
        }
    };
    $loopStep = new Loop($step);
    $loopStep->withInput(new class () extends Step {
        protected function invoke(mixed $input): Generator
        {
            yield (int) substr($input, -1, 1);
        }
    });
    $loopStep->maxIterations(5);
    $result = helper_generatorToArray($loopStep->invokeStep(new Input(0)));
    expect($result[0]->get())->toBe('foo 1');
    expect($result[1]->get())->toBe('foo 2');
    expect($result[2]->get())->toBe('foo 3');
    expect($result[3]->get())->toBe('foo 4');
    expect($result[4]->get())->toBe('foo 5');
});

test('When the step has output but the withInput Closure returns null it stops looping', function () {
    $step = new class () extends Step {
        protected function invoke(mixed $input): Generator
        {
            expect($input)->toBeInt();

            yield $input + 1;
        }
    };
    $loopStep = new Loop($step);
    $loopStep->withInput(function (mixed $input, mixed $output) {
        return $output < 2 ? $output : null;
    });
    $loopStep->maxIterations(5);
    $result = helper_generatorToArray($loopStep->invokeStep(new Input(0)));
    expect($result)->toHaveCount(2);
    expect($result[0]->get())->toBe(1);
    expect($result[1]->get())->toBe(2);
});

test('When the step has output but the withInput Step has no output it stops looping', function () {
    $step = new class () extends Step {
        protected function invoke(mixed $input): Generator
        {
            expect($input)->toBeInt();

            yield $input + 1;
        }
    };
    $loopStep = new Loop($step);
    $loopStep->withInput(new class () extends Step {
        protected function invoke(mixed $input): Generator
        {
            if ($input < 2) {
                yield $input;
            }
        }
    });
    $loopStep->maxIterations(5);
    $result = helper_generatorToArray($loopStep->invokeStep(new Input(0)));
    expect($result)->toHaveCount(2);
    expect($result[0]->get())->toBe(1);
    expect($result[1]->get())->toBe(2);
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
        $results = helper_generatorToArray($loopStep->invokeStep(new Input('test')));
        expect($results[0]->get())->toBe('foo');
        expect($results[1]->get())->toBe('bar');
        expect($results[2]->get())->toBe('baz');
        expect($results[3]->get())->toBe('Lorem');
        expect($results[4]->get())->toBe('Ipsum');
    }
);

test('It doesn\'t output anything when the dontCascade method was called', function () {
    $step = new class () extends Step {
        protected function invoke(mixed $input): Generator
        {
            yield 'something';
        }
    };
    $loopStep = new Loop($step);
    $loopStep->maxIterations(10);

    $results = helper_generatorToArray($loopStep->invokeStep(new Input('foo')));
    expect($results)->toBeArray();
    expect($results)->toHaveCount(10);

    $loopStep->dontCascade();
    $results = helper_generatorToArray($loopStep->invokeStep(new Input('foo')));
    expect($results)->toBeArray();
    expect($results)->toHaveCount(0);
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
    $step = new class () extends Step {
        protected function invoke(mixed $input): Generator
        {
            yield 'pew';
        }
    };

    $loopStep = (new Loop($step))->maxIterations(10);

    $loopStep->cascadeWhenFinished();

    $results = helper_generatorToArray($loopStep->invokeStep(new Input('pew')));

    expect($results)->toHaveCount(10);

    $results = helper_generatorToArray($loopStep->invokeStep(new Input('pew')));

    expect($results)->toHaveCount(10); // If it wouldn't reset the previous deferred outputs it would now be 20 outputs
});

test('You can add and call an updateInputUsingOutput callback', function () {
    $step = new class () extends Step {
        protected function invoke(mixed $input): Generator
        {
            yield 1;
        }
    };
    $step = new Loop($step);
    $step->updateInputUsingOutput(function (mixed $input, mixed $output) {
        return $input . ' ' . $output;
    });

    $updatedInput = $step->callUpdateInputUsingOutput(new Input('Boo'), new Output('Yah!'));
    expect($updatedInput)->toBeInstanceOf(Input::class);
    expect($updatedInput->get())->toBe('Boo Yah!');
});

test('It loops reusing the same input that can be updated via a callback when withInput is used', function () {
    $step = new class () extends Step {
        protected function invoke(mixed $input): Generator
        {
            yield array_pop($input) + (array_pop($input) ?? 0);
        }
    };
    $step = new Loop($step);
    $step->withInput(function (mixed $input, mixed $output) {
        $input[] = $output;

        return $input;
    });
    $step->maxIterations(10);

    $results = helper_generatorToArray($step->invokeStep(new Input([1])));
    expect($results[0]->get())->toBe(1);
    expect($results[1]->get())->toBe(2);
    expect($results[2]->get())->toBe(3);
    expect($results[3]->get())->toBe(5);
    expect($results[4]->get())->toBe(8);
    expect($results[5]->get())->toBe(13);
    expect($results[6]->get())->toBe(21);
    expect($results[7]->get())->toBe(34);
    expect($results[8]->get())->toBe(55);
    expect($results[9]->get())->toBe(89);
});

test('It stops looping when the withInput callback returns null', function () {
    $step = new class () extends Step {
        protected function invoke(mixed $input): Generator
        {
            yield $input;
        }
    };
    $step = new Loop($step);
    $step->withInput(function (mixed $input, mixed $output) {
        return $input < 5 ? $input + 1 : null;
    });
    $step->maxIterations(10);

    $results = helper_generatorToArray($step->invokeStep(new Input(1)));
    expect(count($results))->toBe(5);
    expect($results[0]->get())->toBe(1);
    expect($results[1]->get())->toBe(2);
    expect($results[2]->get())->toBe(3);
    expect($results[3]->get())->toBe(4);
    expect($results[4]->get())->toBe(5);
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
        $step = new class () extends Step {
            protected function invoke(mixed $input): Generator
            {
                yield $input + 1;
            }
        };
        $step = new Loop($step);
        $step->maxIterations(10);
        $step->stopIf(function (mixed $input, mixed $output) {
            return $output > 3;
        });

        $results = helper_generatorToArray($step->invokeStep(new Input(0)));
        expect(count($results))->toBe(3);
        expect($results[0]->get())->toBe(1);
        expect($results[1]->get())->toBe(2);
        expect($results[2]->get())->toBe(3);
    }
);
