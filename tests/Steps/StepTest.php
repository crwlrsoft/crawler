<?php

namespace tests\Steps;

use Crwlr\Crawler\Input;
use Crwlr\Crawler\Logger\CliLogger;
use Crwlr\Crawler\Output;
use Crwlr\Crawler\Result;
use Crwlr\Crawler\Steps\Step;
use Generator;
use PHPUnit\Framework\TestCase;
use function tests\helper_getValueReturningStep;
use function tests\helper_invokeStepWithInput;
use function tests\helper_traverseIterable;

function helper_getNumberIncrementingStep(): Step
{
    return new class () extends Step {
        /**
         * @return Generator<int>
         */
        protected function invoke(mixed $input): Generator
        {
            yield $input + 1;
        }
    };
}

/** @var TestCase $this */

test('You can add a logger and it is available within the invoke method', function () {
    $step = new class () extends Step {
        /**
         * @return Generator<string>
         */
        protected function invoke(mixed $input): Generator
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

test('You can add and call an updateInputUsingOutput callback', function () {
    $step = helper_getValueReturningStep('something');

    $step->updateInputUsingOutput(function (mixed $input, mixed $output) {
        return $input . ' ' . $output;
    });

    $updatedInput = $step->callUpdateInputUsingOutput(new Input('Boo'), new Output('Yah!'));

    expect($updatedInput)->toBeInstanceOf(Input::class);

    expect($updatedInput->get())->toBe('Boo Yah!');
});
