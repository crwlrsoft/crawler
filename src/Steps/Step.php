<?php

namespace Crwlr\Crawler\Steps;

use Closure;
use Crwlr\Crawler\Input;
use Crwlr\Crawler\Loader\Http\Messages\RespondedRequest;
use Crwlr\Crawler\Output;
use Crwlr\Crawler\Steps\Loading\Http;
use Crwlr\Url\Url;
use Exception;
use Generator;
use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;
use Symfony\Component\DomCrawler\Crawler;

abstract class Step extends BaseStep
{
    protected ?Closure $updateInputUsingOutput = null;

    protected ?int $maxOutputs = null;

    protected int $currentOutputCount = 0;

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
        if ($this->maxOutputsExceeded()) {
            return;
        }

        $validInputValue = $this->validateAndSanitizeInput($this->getInputKeyToUse($input)->get());

        if ($this->uniqueInput === false || $this->inputOrOutputIsUnique(new Input($validInputValue))) {
            yield from $this->invokeAndYield($validInputValue, $input);
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

    final public function maxOutputs(int $maxOutputs): static
    {
        $this->maxOutputs = $maxOutputs;

        return $this;
    }

    /**
     * If the user set a callback to update the input (see above) => call it.
     */
    final public function callUpdateInputUsingOutput(Input $input, Output $output): Input
    {
        if ($this->updateInputUsingOutput instanceof Closure) {
            return new Input(
                $this->updateInputUsingOutput->call($this, $input->get(), $output->get()),
                $input->result,
                $input->addLaterToResult,
            );
        }

        return $input;
    }

    public function resetAfterRun(): void
    {
        parent::resetAfterRun();

        $this->currentOutputCount = 0;
    }

    /**
     * Validate and sanitize the incoming Input object
     *
     * In child classes you can add this method to validate and sanitize the incoming input. The method is called
     * automatically when the step is invoked within the Crawler and the invoke method receives the validated and
     * sanitized input. Also, you can just return any value from this method and in the invoke method it's again
     * incoming as an Input object.
     *
     * @throws InvalidArgumentException  Throw this if the input value is invalid for this step.
     */
    protected function validateAndSanitizeInput(mixed $input): mixed
    {
        return $input;
    }

    /**
     * @throws InvalidArgumentException
     */
    protected function validateAndSanitizeStringOrStringable(
        mixed $inputValue,
        string $exceptionMessage = 'Input must be string or stringable'
    ): string {
        $inputValue = $this->getSingleElementFromArray($inputValue);

        if (is_object($inputValue) && method_exists($inputValue, '__toString')) {
            return $inputValue->__toString();
        }

        if (is_string($inputValue)) {
            return $inputValue;
        }

        throw new InvalidArgumentException($exceptionMessage);
    }

    /**
     * @throws InvalidArgumentException
     */
    protected function validateAndSanitizeStringOrHttpResponse(
        mixed $inputValue,
        string $exceptionMessage = 'Input must be string, stringable or HTTP response (RespondedRequest)',
        bool $allowOnlyRespondedRequest = false
    ): string {
        $inputValue = $this->getSingleElementFromArray($inputValue);

        if (
            $inputValue instanceof RespondedRequest ||
            ($inputValue instanceof ResponseInterface && !$allowOnlyRespondedRequest)
        ) {
            return Http::getBodyString($inputValue);
        }

        return $this->validateAndSanitizeStringOrStringable($inputValue, $exceptionMessage);
    }

    /**
     * @throws InvalidArgumentException
     */
    protected function validateAndSanitizeToUriInterface(
        mixed $inputValue,
        string $exceptionMessage = 'Input must be string, stringable or an instance of UriInterface or Crwlr\\Url',
    ): UriInterface {
        $inputValue = $this->getSingleElementFromArray($inputValue);

        if ($inputValue instanceof UriInterface) {
            return $inputValue;
        }

        if (
            is_string($inputValue) ||
            $inputValue instanceof Url ||
            (is_object($inputValue) && method_exists($inputValue, '__toString'))
        ) {
            return Url::parsePsr7((string) $inputValue);
        }

        throw new InvalidArgumentException($exceptionMessage);
    }

    protected function validateAndSanitizeToDomCrawlerInstance(
        mixed $inputValue,
        string $exceptionMessage = 'Input must be string, stringable or HTTP response (RespondedRequest)',
    ): Crawler {
        return new Crawler($this->validateAndSanitizeStringOrHttpResponse($inputValue, $exceptionMessage));
    }

    /**
     * @throws Exception
     */
    private function invokeAndYield(mixed $validInputValue, Input $input): Generator
    {
        foreach ($this->invoke($validInputValue) as $outputData) {
            if ($this->maxOutputsExceeded() || !$this->passesAllFilters($outputData)) {
                continue;
            }

            if (!is_array($outputData) && $this->outputKey) {
                $outputData = [$this->outputKey => $outputData];
            }

            if ($this->keepInputData === true) {
                $outputData = $this->addInputDataToOutputData($input->get(), $outputData);
            }

            $output = $this->makeOutput($outputData, $input);

            if ($this->uniqueOutput && !$this->inputOrOutputIsUnique($output)) {
                continue;
            }

            yield $output;

            if ($this->maxOutputs !== null) {
                $this->currentOutputCount += 1;
            }
        }
    }

    private function maxOutputsExceeded(): bool
    {
        return $this->maxOutputs !== null && $this->currentOutputCount >= $this->maxOutputs;
    }

    private function getSingleElementFromArray(mixed $inputValue): mixed
    {
        if (is_array($inputValue) && count($inputValue) === 1) {
            return reset($inputValue);
        }

        return $inputValue;
    }
}
