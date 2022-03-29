<?php

namespace Crwlr\Crawler\Steps;

use Closure;
use Crwlr\Crawler\Input;
use Crwlr\Crawler\Output;
use Crwlr\Crawler\Result;
use Exception;
use Generator;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;

abstract class Step implements StepInterface
{
    protected LoggerInterface $logger;
    protected ?Closure $inputMutationCallback = null;
    protected ?string $resultKey = null;
    protected ?string $useInputKey = null;
    protected bool $cascades = true;
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
        if ($this->useInputKey !== null && (is_array($input->get()) || !isset($input->get()[$this->useInputKey]))) {
            throw new Exception('Key ' . $this->useInputKey . ' does not exist in input');
        }

        $inputValue = $this->useInputKey ? $input->get()[$this->useInputKey] : $input->get();

        $validInput = $this->validateAndSanitizeInput($inputValue);

        foreach ($this->invoke($validInput) as $output) {
            yield $this->output($output, $input->result);
        }
    }

    public function useInputKey(string $key): static
    {
        $this->useInputKey = $key;

        return $this;
    }

    public function setResultKey(string $key): static
    {
        $this->resultKey = $key;

        return $this;
    }

    public function getResultKey(): ?string
    {
        return $this->resultKey;
    }

    public function dontCascade(): static
    {
        $this->cascades = false;

        return $this;
    }

    public function cascades(): bool
    {
        return $this->cascades;
    }

    /**
     * Callback that is called in a step group to adapt the input for further steps
     *
     * In groups all the steps are called with the same Input, but with this callback it's possible to adjust the input
     * for the following steps.
     */
    public function updateInputUsingOutput(Closure $closure): static
    {
        $this->updateInputUsingOutput = $closure;

        return $this;
    }

    /**
     * If the user set a callback to update the input (see above) => call it.
     */
    public function callUpdateInputUsingOutput(Input $input, Output $output): Input
    {
        if ($this->updateInputUsingOutput instanceof Closure) {
            return new Input($this->updateInputUsingOutput->call($this, $input->get(), $output->get()), $input->result);
        }

        return $input;
    }

    final public function addLogger(LoggerInterface $logger): static
    {
        $this->logger = $logger;

        return $this;
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

    /**
     * Use this method when returning values from the invoke method
     *
     * It assures that steps always return an array, all values are wrapped in Output objects, and it handles building
     * Results.
     *
     * @throws Exception
     */
    protected function output(mixed $value, ?Result $result = null): Output
    {
        if ($this->resultKey !== null) {
            $result = $result ?? new Result();
            $result->set($this->resultKey, $value);
        }

        return new Output($value, $result);
    }
}
