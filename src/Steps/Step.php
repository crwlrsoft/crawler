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
    protected bool $yield = true;

    /**
     * @return Generator<mixed>
     */
    abstract protected function invoke(Input $input): Generator;

    /**
     * Calls the validateAndSanitizeInput method and assures that the invoke method receives valid, sanitized input.
     *
     * @return Generator<Output>
     * @throws Exception
     */
    final public function invokeStep(Input $input): Generator
    {
        if ($this->useInputKey !== null) {
            if (!array_key_exists($this->useInputKey, $input->get())) {
                throw new Exception('Key ' . $this->useInputKey . ' does not exist in input');
            }

            $input = new Input($input->get()[$this->useInputKey], $input->result);
        }

        $validInput = new Input($this->validateAndSanitizeInput($input), $input->result);

        foreach ($this->invoke($validInput) as $output) {
            if ($this->yield) {
                yield $this->output($output, $validInput);
            }
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

    public function dontYield(): static
    {
        $this->yield = false;

        return $this;
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
    protected function validateAndSanitizeInput(Input $input): mixed
    {
        return $input->get();
    }

    /**
     * Use this method when returning values from the invoke method
     *
     * It assures that steps always return an array, all values are wrapped in Output objects, and it handles building
     * Results.
     *
     * @throws Exception
     */
    protected function output(mixed $value, Input $input): Output
    {
        if ($this->resultKey !== null) {
            if (!$input->result) {
                $input->result = new Result();
            }

            $input->result->set($this->resultKey, $value);
        }

        return new Output($value, $input->result);
    }
}
