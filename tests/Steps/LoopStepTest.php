<?php

namespace tests\Steps;

use Crwlr\Crawler\Input;
use Crwlr\Crawler\Logger\CliLogger;
use Crwlr\Crawler\Output;
use Crwlr\Crawler\Steps\LoopStep;
use Crwlr\Crawler\Steps\Step;
use Crwlr\Crawler\Steps\StepInterface;
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

            protected function invoke(Input $input): Generator
            {
                if ($this->_callCount === 0) {
                    expect($input->get())->toBe('foo');
                } else {
                    expect($input->get())->toBe($this->_callCount);
                }

                $this->_callCount++;

                if ($this->_callCount < 5) {
                    yield $this->_callCount;
                }
            }
        };
        $loopStep = new LoopStep($step);
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

            protected function invoke(Input $input): Generator
            {
                $this->_callCount++;

                if ($this->_callCount <= $this->yieldUntilRepetition) {
                    yield $this->_callCount;
                }
            }
        };
        $loopStep = new LoopStep($step);
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

        protected function invoke(Input $input): Generator
        {
            $this->_callCount++;
            yield $this->_callCount;
        }
    };
    $loopStep = new LoopStep($step);
    $loopStep->maxIterations($customLimit);
    helper_traverseIterable($loopStep->invokeStep(new Input('foo')));
    expect($step->_callCount)->toBe($customLimit);
})->with([10, 100, 100000]);

test('You can use a Closure to transform an iterations output to the input for the next step', function () {
    $step = new class () extends Step {
        protected function invoke(Input $input): Generator
        {
            expect($input->get())->toBeInt();

            yield 'output ' . ($input->get() + 1);
        }
    };
    $loopStep = new LoopStep($step);
    $loopStep->transformOutputToInput(function (Output $output) {
        $outputValue = $output->get();

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

test('You can use a step as output to input transformer', function () {
    $step = new class () extends Step {
        protected function invoke(Input $input): Generator
        {
            expect($input->get())->toBeInt();

            yield 'foo ' . ($input->get() + 1);
        }
    };
    $loopStep = new LoopStep($step);
    $loopStep->transformOutputToInput(new class () extends Step {
        protected function invoke(Input $input): Generator
        {
            yield (int) substr($input->get(), -1, 1);
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

test('When the step has output but the transformer Closure returns null it stops looping', function () {
    $step = new class () extends Step {
        protected function invoke(Input $input): Generator
        {
            expect($input->get())->toBeInt();

            yield $input->get() + 1;
        }
    };
    $loopStep = new LoopStep($step);
    $loopStep->transformOutputToInput(function (Output $output) {
        return $output->get() < 2 ? $output->get() : null;
    });
    $loopStep->maxIterations(5);
    $result = helper_generatorToArray($loopStep->invokeStep(new Input(0)));
    expect($result)->toHaveCount(2);
    expect($result[0]->get())->toBe(1);
    expect($result[1]->get())->toBe(2);
});

test('When the step has output but the transformer Step has no output it stops looping', function () {
    $step = new class () extends Step {
        protected function invoke(Input $input): Generator
        {
            expect($input->get())->toBeInt();

            yield $input->get() + 1;
        }
    };
    $loopStep = new LoopStep($step);
    $loopStep->transformOutputToInput(new class () extends Step {
        protected function invoke(Input $input): Generator
        {
            if ($input->get() < 2) {
                yield $input->get();
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
    $loopStep = new LoopStep($step);
    $loopStep->addLogger(new CliLogger());
});

test(
    'When the step yields multiple outputs, it loops with and outputs each one of them',
    function () {
        $step = Mockery::mock(StepInterface::class);

        // Initial call returning 3 outputs
        $step->shouldReceive('invokeStep')->once()->andReturn(
            helper_arrayToGenerator([new Output('foo'), new Output('bar'), new Output('baz')])
        );

        // Looping calls with outputs of first call, first one has no output, second has one and third has two outputs
        // again
        $step->shouldReceive('invokeStep')->once()->withArgs(function (Input $input) {
            return $input->get() === 'foo';
        })->andReturn(helper_arrayToGenerator([]));
        $step->shouldReceive('invokeStep')->once()->withArgs(function (Input $input) {
            return $input->get() === 'bar';
        })->andReturn(helper_arrayToGenerator([new Output('Lorem')]));
        $step->shouldReceive('invokeStep')->once()->withArgs(function (Input $input) {
            return $input->get() === 'baz';
        })->andReturn(helper_arrayToGenerator([new Output('Ipsum'), new Output('Dolor')]));

        // 3 calls again resulting from the outputs of the second and third call from previous block
        $step->shouldReceive('invokeStep')->once()->withArgs(function (Input $input) {
            return $input->get() === 'Lorem';
        })->andReturn(helper_arrayToGenerator([]));
        $step->shouldReceive('invokeStep')->once()->withArgs(function (Input $input) {
            return $input->get() === 'Ipsum';
        })->andReturn(helper_arrayToGenerator([]));
        $step->shouldReceive('invokeStep')->once()->withArgs(function (Input $input) {
            return $input->get() === 'Dolor';
        })->andReturn(helper_arrayToGenerator([]));

        $loopStep = new LoopStep($step);
        $results = helper_generatorToArray($loopStep->invokeStep(new Input('test')));
        expect($results[0]->get())->toBe('foo');
        expect($results[1]->get())->toBe('bar');
        expect($results[2]->get())->toBe('baz');
        expect($results[3]->get())->toBe('Lorem');
        expect($results[4]->get())->toBe('Ipsum');
        expect($results[5]->get())->toBe('Dolor');
    }
);

test('It doesn\'t yield anything when the dontYield method was called', function () {
    $step = new class () extends Step {
        protected function invoke(Input $input): Generator
        {
            yield 'something';
        }
    };
    $loopStep = new LoopStep($step);
    $loopStep->maxIterations(10);

    $results = helper_generatorToArray($loopStep->invokeStep(new Input('foo')));
    expect($results)->toBeArray();
    expect($results)->toHaveCount(10);

    $loopStep->dontYield();
    $results = helper_generatorToArray($loopStep->invokeStep(new Input('foo')));
    expect($results)->toBeArray();
    expect($results)->toHaveCount(0);
});
