<?php

namespace Crwlr\Crawler\Steps;

use Closure;
use Crwlr\Crawler\Input;
use Crwlr\Crawler\Output;
use Crwlr\Crawler\Result;
use Exception;
use Generator;
use InvalidArgumentException;

abstract class Step extends BaseStep
{
    protected ?Closure $updateInputUsingOutput = null;

    /**
     * @return Generator<mixed>
     */
    abstract protected function invoke(mixed $input): Generator;

    /**
     * Calls the validateAndSanitizeInput method and assures that the invoke method receives valid, sanitized input.
     *
     * @return Generator<Output>
     * @throws Exception
     */
    final public function invokeStep(Input $input): Generator
    {
        $input = $this->getInputKeyToUse($input);

        $validInputValue = $this->validateAndSanitizeInput($input->get());

        if ($this->uniqueOutput) {
            yield from $this->invokeAndYieldUnique($validInputValue, $input->result);
        } else {
            yield from $this->invokeAndYield($validInputValue, $input->result);
        }
    }

    /**
     * Callback that is called in a step group to adapt the input for further steps
     *
     * In groups all the steps are called with the same Input, but with this callback it's possible to adjust the input
     * for the following steps.
     */
    final public function updateInputUsingOutput(Closure $closure): static
    {
        $this->updateInputUsingOutput = $closure;

        return $this;
    }

    /**
     * If the user set a callback to update the input (see above) => call it.
     */
    final public function callUpdateInputUsingOutput(Input $input, Output $output): Input
    {
        if ($this->updateInputUsingOutput instanceof Closure) {
            return new Input($this->updateInputUsingOutput->call($this, $input->get(), $output->get()), $input->result);
        }

        return $input;
    }

    /**
     * Validate and sanitize the incoming Input object
     *
     * In child classes you can add this method to validate and sanitize the incoming input. The method is called
     * automatically when the step is invoked within the Crawler and the invoke method receives the validated and
     * sanitized input. Also you can just return any value from this method and in the invoke method it's again
     * incoming as an Input object.
     *
     * @throws InvalidArgumentException  Throw this if the input value is invalid for this step.
     */
    protected function validateAndSanitizeInput(mixed $input): mixed
    {
        return $input;
    }

    private function invokeAndYield(mixed $validInputValue, ?Result $result): Generator
    {
        foreach ($this->invoke($validInputValue) as $output) {
            if ($this->passesAllFilters($output)) {
                yield $this->output($output, $result);
            }
        }
    }

    private function invokeAndYieldUnique(mixed $validInputValue, ?Result $result): Generator
    {
        foreach ($this->invoke($validInputValue) as $output) {
            if (!$this->passesAllFilters($output)) {
                continue;
            }

            $output = $this->output($output, $result);

            $key = is_string($this->uniqueOutput) ? $output->setKey($this->uniqueOutput) : $output->setKey();

            if (isset($this->uniqueOutputKeys[$key])) {
                continue;
            }

            $this->uniqueOutputKeys[$key] = true; // Don't keep output value, just the key, to keep memory usage low.

            yield $output;
        }
    }

    /**
     * Wrap a single output yielded in the invoke method in an Output object and handle adding data to the final Result.
     *
     * @throws Exception
     */
    private function output(mixed $output, ?Result $result = null): Output
    {
        $result = $this->addOutputDataToResult($output, $result);

        return new Output($output, $result);
    }
}
