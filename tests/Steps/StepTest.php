<?php

namespace tests\Steps;

use Crwlr\Crawler\Input;
use Crwlr\Crawler\Logger\CliLogger;
use Crwlr\Crawler\Output;
use Crwlr\Crawler\Result;
use Crwlr\Crawler\Steps\Step;
use Generator;
use PHPUnit\Framework\TestCase;
use function tests\helper_generatorToArray;
use function tests\helper_traverseIterable;

function helper_getNumberIncrementingStep(): Step
{
    return new class () extends Step {
        /**
         * @return Generator<int>
         */
        protected function invoke(Input $input): Generator
        {
            yield $input->get() + 1;
        }
    };
}

/** @var TestCase $this */

test('You can add a logger and it is available within the invoke method', function () {
    $step = new class () extends Step {
        /**
         * @return Generator<string>
         */
        protected function invoke(Input $input): Generator
        {
            $this->logger->info('logging works');
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
        $step = new class () extends Step {
            /**
             * @return Generator<string>
             */
            protected function invoke(Input $input): Generator
            {
                yield 'returnValue';
            }
        };
        $output = $step->invokeStep(new Input('inputValue'));
        $output = iterator_to_array($output);
        expect($output)->toHaveCount(1);
        expect($output[0])->toBeInstanceOf(Output::class);
        expect($output[0]->get())->toBe('returnValue');
        expect($output[0]->result)->toBeNull();
    }
);

test(
    'The invokeStep method creates a Result object that is added to the Output when you set a property name',
    function () {
        $step = new class () extends Step {
            /**
             * @return Generator<string>
             */
            protected function invoke(Input $input): Generator
            {
                yield 'returnValue';
            }
        };
        $step->setResultKey('property');
        $output = $step->invokeStep(new Input('inputValue'));
        $output = helper_generatorToArray($output);

        expect($output[0]->result)->toBeInstanceOf(Result::class);
        expect($output[0]->result->toArray())->toBe(['property' => 'returnValue']); // @phpstan-ignore-line
    }
);

test('It doesn\'t add the result object to the Input object only to the Output', function () {
    $step = new class () extends Step {
        protected function invoke(Input $input): Generator
        {
            yield 'Stand with Ukraine!';
        }
    };
    $step->setResultKey('property');
    $input = new Input('inputValue');
    $output = helper_generatorToArray($step->invokeStep($input));

    expect($output[0]->result)->toBeInstanceOf(Result::class);
    expect($input->result)->toBe(null);
});

test(
    'The invokeStep method appends properties to a result object that was already included with the Input object',
    function () {
        $step = new class () extends Step {
            /**
             * @return Generator<string>
             */
            protected function invoke(Input $input): Generator
            {
                yield 'returnValue';
            }
        };
        $step->setResultKey('property');
        $prevResult = new Result();
        $prevResult->set('prevProperty', 'foobar');
        $output = $step->invokeStep(new Input('inputValue', $prevResult));
        $output = helper_generatorToArray($output);
        expect($output[0]->result)->toBeInstanceOf(Result::class);
        expect($output[0]->result->toArray())->toBe([ // @phpstan-ignore-line
            'prevProperty' => 'foobar',
            'property' => 'returnValue',
        ]);
    }
);

test(
    'The invokeStep method also passes on Result objects through further steps when they don\'t define further ' .
    'result resource properties',
    function () {
        $step = new class () extends Step {
            /**
             * @return Generator<string>
             */
            protected function invoke(Input $input): Generator
            {
                yield 'returnValue';
            }
        };
        $prevResult = new Result();
        $prevResult->set('prevProperty', 'foobar');
        $output = $step->invokeStep(new Input('inputValue', $prevResult));
        $output = helper_generatorToArray($output);
        expect($output[0]->result)->toBeInstanceOf(Result::class);
        expect($output[0]->result->toArray())->toBe([ // @phpstan-ignore-line
            'prevProperty' => 'foobar',
        ]);
    }
);

test('The invokeStep method calls the validateAndSanitizeInput method', function () {
    $step = new class () extends Step {
        protected function validateAndSanitizeInput(Input $input): string
        {
            return $input->get() . ' validated and sanitized';
        }

        /**
         * @return Generator<string>
         */
        protected function invoke(Input $input): Generator
        {
            yield $input->get();
        }
    };
    $output = $step->invokeStep(new Input('inputValue'));
    $output = iterator_to_array($output);
    expect($output[0]->get())->toBe('inputValue validated and sanitized');
});

test('It is possible that a step does not produce any output at all', function () {
    $step = new class () extends Step {
        /**
         * @return Generator<string>
         */
        protected function invoke(Input $input): Generator
        {
            if ($input->get() === 'foo') {
                yield 'bar';
            }
        }
    };

    $output = $step->invokeStep(new Input('lol'));
    $output = helper_generatorToArray($output);
    expect($output)->toHaveCount(0);

    $output = $step->invokeStep(new Input('foo'));
    $output = helper_generatorToArray($output);
    expect($output)->toHaveCount(1);
    expect($output[0]->get())->toBe('bar');
});

test('It doesn\'t yield anything when the dontYield method was called', function () {
    $step = new class () extends Step {
        /**
         * @return Generator<string>
         */
        protected function invoke(Input $input): Generator
        {
            yield 'something';
        }
    };

    $output = helper_generatorToArray($step->invokeStep(new Input('yield')));
    expect($output)->toHaveCount(1);

    $step->dontYield();
    $output = helper_generatorToArray($step->invokeStep(new Input('yield')));
    expect($output)->toHaveCount(0);
});

test('You can add and call an updateInputUsingOutput callback', function () {
    $step = new class () extends Step {
        /**
         * @return Generator<string>
         */
        protected function invoke(Input $input): Generator
        {
            yield 'something';
        }
    };
    $step->updateInputUsingOutput(function (Input $input, Output $output) {
        return $input->get() . ' ' . $output->get();
    });

    $updatedInput = $step->callUpdateInputUsingOutput(new Input('Boo'), new Output('Yah!'));
    expect($updatedInput)->toBeInstanceOf(Input::class);
    expect($updatedInput->get())->toBe('Boo Yah!');
});
