<?php

namespace Crwlr\Crawler\Steps;

use Crwlr\Crawler\Input;
use Crwlr\Crawler\Output;
use Crwlr\Crawler\Result;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;

abstract class Step implements StepInterface
{
    protected LoggerInterface $logger;
    private ?string $resultResourceName = null;
    private ?string $resultResourcePropertyName = null;

    abstract protected function invoke(Input $input): array;

    /**
     * Calls the validateAndSanitizeInput method and assures that the invoke method receives valid, sanitized input.
     */
    final public function invokeStep(Input $input): array
    {
        $validInput = new Input($this->validateAndSanitizeInput($input), $input->result);

        return $this->invoke($validInput);
    }

    final public function addLogger(LoggerInterface $logger): static
    {
        $this->logger = $logger;

        return $this;
    }

    public function initResultResource(string $resultResourceName): static
    {
        $this->resultResourceName = $resultResourceName;

        return $this;
    }

    public function resultResourceProperty(string $propertyName): static
    {
        $this->resultResourcePropertyName = $propertyName;

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
     */
    protected function output(mixed $values, Input $input): array
    {
        $outputs = [];
        $values = is_array($values) ? $values : [$values];

        foreach ($values as $value) {
            if ($this->resultResourceName) {
                $result = new Result($this->resultResourceName);
                $result->setProperty($this->resultResourcePropertyName, $value);
                $outputs[] = new Output($value, $result);
            } else {
                if ($this->resultResourcePropertyName) {
                    $input->result->setProperty($this->resultResourcePropertyName, $value);
                }

                $outputs[] = new Output($value, $input->result);
            }
        }

        return $outputs;
    }
}
