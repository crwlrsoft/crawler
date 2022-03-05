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
    protected bool $repeat = false;
    protected bool $repeatWithInput = false;
    protected bool $repeatWithOutput = false;
    protected ?Closure $inputMutationCallback = null;
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
                        if ($this->repeatWithInput && $this->inputMutationCallback) {
                            $newInput = new Input(
                                $this->inputMutationCallback->call($this, $validInput),
                                $validInput->result
                            );
                        } elseif ($this->repeatWithInput) {
                            $newInput = $validInput;
                        } else {
                            $newInput = new Input($output);
                        }

                        yield from $this->invokeStep($newInput);
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
        $this->repeatWithOutput = true;
        $this->maxRepetitions = $maxRepetitions;

        return $this;
    }

    public function repeatWithInputUntilNoMoreResults(
        int $maxRepetitions = 100,
        ?Closure $inputMutationCallback = null
    ): static {
        $this->repeat = true;
        $this->repeatWithInput = true;
        $this->inputMutationCallback = $inputMutationCallback;
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
        if ($this->resultResourcePropertyName) {
            if (!$input->result) {
                $input->result = new Result();
            }

            $input->result->set($this->resultResourcePropertyName, $value);
        }

        return new Output($value, $input->result);
    }
}
