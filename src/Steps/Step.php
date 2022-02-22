<?php

namespace Crwlr\Crawler\Steps;

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
    protected bool $repeat = false;
    protected int $maxRepetitions = 100;
    protected int $repetitions = 0;
    private ?string $resultResourceName = null;
    private ?string $resultResourcePropertyName = null;

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
        if ($this->repeat === true) {
            $inputs = [$input];

            foreach ($inputs as $input) {
                $validInput = new Input($this->validateAndSanitizeInput($input), $input->result);
                $this->repetitions++;

                foreach ($this->invoke($validInput) as $output) {
                    $output = $this->output($output, $validInput);

                    yield $output;

                    if ($this->repetitions < $this->maxRepetitions) {
                        yield from $this->invokeStep(new Input($output));
                    } else {
                        $this->logger->warning(
                            'Stop repeating step as max repitions of ' . $this->maxRepetitions . ' are reached.'
                        );
                    }
                }
            }
        } else {
            $validInput = new Input($this->validateAndSanitizeInput($input), $input->result);

            foreach ($this->invoke($validInput) as $output) {
                $output = $this->output($output, $validInput);
                yield $output;
            }
        }
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

    public function resultDefined(): bool
    {
        return $this->resultResourceName !== null || $this->resultResourcePropertyName !== null;
    }

    public function repeatWithOutputUntilNoMoreResults(int $maxRepetitions = 100): static
    {
        $this->repeat = true;
        $this->maxRepetitions = $maxRepetitions;

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
        if ($this->resultResourceName) {
            $result = new Result($this->resultResourceName);

            if ($this->resultResourcePropertyName === null) {
                throw new Exception('No resource property defined');
            }

            $result->set($this->resultResourcePropertyName, $value);

            return new Output($value, $result);
        }

        if ($this->resultResourcePropertyName) {
            if (!$input->result) {
                throw new Exception('Defined a resource property name but no resource was initialized yet!');
            }

            $input->result->set($this->resultResourcePropertyName, $value);
        }

        return new Output($value, $input->result);
    }
}
