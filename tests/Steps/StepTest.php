<?php

namespace tests\Steps;

use Crwlr\Crawler\Input;
use Crwlr\Crawler\Logger\CliLogger;
use Crwlr\Crawler\Output;
use Crwlr\Crawler\Result;
use Crwlr\Crawler\Steps\Step;
use PHPUnit\Framework\TestCase;

/** @var TestCase $this */

test('You can add a logger and it is available within the invoke method', function () {
    $step = new class extends Step {
        protected function invoke(Input $input): array
        {
            $this->logger->info('logging works');

            return $this->output('something', $input);
        }
    };
    $step->addLogger(new CliLogger());
    $step->invokeStep(new Input('test'));
    $output = $this->getActualOutput();
    expect($output)->toContain('logging works');
});

test(
    'The output method returns an array and wraps the return values in Output objects by default without Result objects',
    function () {
        $step = new class extends Step {
            protected function invoke(Input $input): array
            {
                return $this->output('returnValue', $input);
            }
        };
        $output = $step->invokeStep(new Input('inputValue'));
        expect($output)->toHaveCount(1);
        expect($output[0])->toBeInstanceOf(Output::class);
        expect($output[0]->get())->toBe('returnValue');
        expect($output[0]->result)->toBeNull();
    }
);

test(
    'The output method creates a Result object that is added to the Output object when you define a result resource',
    function () {
        $step = new class extends Step {
            protected function invoke(Input $input): array
            {
                return $this->output('returnValue', $input);
            }
        };
        $step->initResultResource('someResource')
            ->resultResourceProperty('property');
        $output = $step->invokeStep(new Input('inputValue'));
        expect($output[0]->result)->toBeInstanceOf(Result::class);
        expect($output[0]->result->toArray())->toBe(['property' => 'returnValue']); // @phpstan-ignore-line
    }
);

test(
    'The output method appends properties to a result object that was already included with the Input object',
    function () {
        $step = new class extends Step {
            protected function invoke(Input $input): array
            {
                return $this->output('returnValue', $input);
            }
        };
        $step->resultResourceProperty('property');
        $prevResult = new Result('someResource');
        $prevResult->setProperty('prevProperty', 'foobar');
        $output = $step->invokeStep(new Input('inputValue', $prevResult));
        expect($output[0]->result)->toBeInstanceOf(Result::class);
        expect($output[0]->result->toArray())->toBe([ // @phpstan-ignore-line
            'prevProperty' => 'foobar',
            'property' => 'returnValue',
        ]);
    }
);

test('The invokeStep method calls the validateAndSanitizeInput method', function () {
    $step = new class extends Step {
        public function validateAndSanitizeInput(Input $input): mixed
        {
            return $input->get() . ' validated and sanitized';
        }

        protected function invoke(Input $input): array
        {
            return $this->output($input->get(), $input);
        }
    };
    $output = $step->invokeStep(new Input('inputValue'));
    expect($output[0]->get())->toBe('inputValue validated and sanitized');
});
