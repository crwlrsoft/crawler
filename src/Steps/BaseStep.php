<?php

namespace Crwlr\Crawler\Steps;

use Crwlr\Crawler\Input;
use Crwlr\Crawler\Io;
use Crwlr\Crawler\Output;
use Crwlr\Crawler\Result;
use Crwlr\Crawler\Steps\Filters\FilterInterface;
use Exception;
use Generator;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;

/**
 * Base class for classes Step and Group which share some things in terms of adding output data to Result objects.
 */

abstract class BaseStep implements StepInterface
{
    /**
     * True means add all elements of the output array.
     * String means use that key for a non array output value.
     * Array of strings means, add just those keys.
     *
     * @var bool|string|string[]
     */
    protected bool|string|array $addToResult = false;

    /**
     * Same as $addToResult, but doesn't create a Result object now. Instead, it appends the data to the Output object,
     * so it'll add the data to all the Result objects that are later created from the output.
     *
     * @var bool|string|string[]
     */
    protected bool|string|array $addLaterToResult = false;

    protected ?LoggerInterface $logger = null;

    protected ?string $useInputKey = null;

    protected bool|string $uniqueInput = false;

    /**
     * @var array<int|string, true>
     */
    protected array $uniqueInputKeys = [];

    protected bool|string $uniqueOutput = false;

    /**
     * @var array<int|string, true>
     */
    protected array $uniqueOutputKeys = [];

    /**
     * @var FilterInterface[]
     */
    protected array $filters = [];

    protected bool $keepInputData = false;

    protected ?string $keepInputDataKey = null;

    protected ?string $outputKey = null;

    /**
     * @param Input $input
     * @return Generator<Output>
     */
    abstract public function invokeStep(Input $input): Generator;

    public function addLogger(LoggerInterface $logger): static
    {
        $this->logger = $logger;

        return $this;
    }

    public function addToResult(array|string|null $keys = null): static
    {
        if (is_string($keys) || is_array($keys)) {
            $this->addToResult = $keys;
        } else {
            $this->addToResult = true;
        }

        return $this;
    }

    public function addLaterToResult(array|string|null $keys = null): static
    {
        if (is_string($keys) || is_array($keys)) {
            $this->addLaterToResult = $keys;
        } else {
            $this->addLaterToResult = true;
        }

        return $this;
    }

    public function addsToOrCreatesResult(): bool
    {
        return $this->createsResult() || $this->addLaterToResult !== false;
    }

    public function createsResult(): bool
    {
        return $this->addToResult !== false;
    }

    final public function useInputKey(string $key): static
    {
        $this->useInputKey = $key;

        return $this;
    }

    final public function uniqueInputs(?string $key = null): static
    {
        $this->uniqueInput = $key ?? true;

        return $this;
    }

    final public function uniqueOutputs(?string $key = null): static
    {
        $this->uniqueOutput = $key ?? true;

        return $this;
    }

    final public function where(string|FilterInterface $keyOrFilter, ?FilterInterface $filter = null): static
    {
        if (is_string($keyOrFilter) && $filter === null) {
            throw new InvalidArgumentException('You have to provide a Filter (instance of FilterInterface)');
        } elseif (is_string($keyOrFilter)) {
            $filter->useKey($keyOrFilter);

            $this->filters[] = $filter;
        } else {
            $this->filters[] = $keyOrFilter;
        }

        return $this;
    }

    /**
     * @throws Exception
     */
    final public function orWhere(string|FilterInterface $keyOrFilter, ?FilterInterface $filter = null): static
    {
        if (empty($this->filters)) {
            throw new Exception('No where before orWhere');
        } elseif (is_string($keyOrFilter) && $filter === null) {
            throw new InvalidArgumentException('You have to provide a Filter (instance of FilterInterface)');
        } elseif (is_string($keyOrFilter)) {
            $filter->useKey($keyOrFilter);
        } else {
            $filter = $keyOrFilter;
        }

        $lastFilter = end($this->filters);

        $lastFilter->addOr($filter);

        return $this;
    }

    public function outputKey(string $key): static
    {
        $this->outputKey = $key;

        return $this;
    }

    /**
     * @param string|null $inputKey
     * @return $this
     */
    public function keepInputData(?string $inputKey = null): static
    {
        $this->keepInputData = true;

        $this->keepInputDataKey = $inputKey;

        return $this;
    }

    public function resetAfterRun(): void
    {
        $this->uniqueOutputKeys = $this->uniqueInputKeys = [];
    }

    /**
     * @throws Exception
     */
    protected function getInputKeyToUse(Input $input): Input
    {
        if ($this->useInputKey !== null) {
            if (!array_key_exists($this->useInputKey, $input->get())) {
                throw new Exception('Key ' . $this->useInputKey . ' does not exist in input');
            }

            $input = new Input($input->get()[$this->useInputKey], $input->result, $input->addLaterToResult);
        }

        return $input;
    }

    protected function inputOrOutputIsUnique(Io $io): bool
    {
        $uniquenessSetting = $io instanceof Input ? $this->uniqueInput : $this->uniqueOutput;

        $uniqueKeys = $io instanceof Input ? $this->uniqueInputKeys : $this->uniqueOutputKeys;

        $key = is_string($uniquenessSetting) ? $io->setKey($uniquenessSetting) : $io->setKey();

        if (isset($uniqueKeys[$key])) {
            return false;
        }

        if ($io instanceof Input) {
            $this->uniqueInputKeys[$key] = true; // Don't keep value, just the key, to keep memory usage low.
        } else {
            $this->uniqueOutputKeys[$key] = true;
        }

        return true;
    }

    protected function passesAllFilters(mixed $output): bool
    {
        foreach ($this->filters as $filter) {
            if (!$filter->evaluate($output)) {
                if ($filter->getOr()) {
                    $orFilter = $filter->getOr();

                    while ($orFilter) {
                        if ($orFilter->evaluate($output)) {
                            continue 2;
                        }

                        $orFilter = $orFilter->getOr();
                    }
                }

                return false;
            }
        }

        return true;
    }

    /**
     * @return array<string, mixed>
     * @throws Exception
     */
    protected function addInputDataToOutputData(mixed $inputValue, mixed $outputValue): array
    {
        if (!is_array($outputValue)) {
            throw new Exception(
                'Can\'t add input data to non array output data! You can use the outputKey() method ' .
                'to make the step\'s output an array.'
            );
        }

        if (!is_array($inputValue)) {
            if (!is_string($this->keepInputDataKey)) {
                throw new Exception('No key defined for scalar input value.');
            }

            $inputValue = [$this->keepInputDataKey => $inputValue];
        }

        foreach ($inputValue as $key => $value) {
            if (!isset($outputValue[$key])) {
                $outputValue[$key] = $value;
            }
        }

        return $outputValue;
    }

    protected function makeOutput(mixed $outputData, Input $input): Output
    {
        return new Output(
            $outputData,
            $this->addOutputDataToResult($outputData, $input),
            $this->addOutputDataToAddLaterResult($outputData, $input),
        );
    }

    protected function addOutputDataToResult(
        mixed $output,
        Input $input,
    ): ?Result {
        if ($this->addToResult !== false) {
            $result = $input->result ?? new Result($input->addLaterToResult);

            return $this->addOutputDataToResultObject($output, $result);
        }

        return $input->result;
    }

    protected function addOutputDataToAddLaterResult(mixed $output, Input $input): ?Result
    {
        if ($this->addToResult !== false) {
            return null;
        }

        if ($this->addLaterToResult !== false) {
            $addLaterResult = $input->addLaterToResult ?? new Result();

            return $this->addOutputDataToResultObject($output, $addLaterResult);
        }

        return $input->addLaterToResult;
    }

    protected function addOutputDataToResultObject(mixed $output, Result $result): Result
    {
        $addToResultObject = $this->addToResult !== false ? $this->addToResult : $this->addLaterToResult;

        if (is_string($addToResultObject)) {
            $result->set($addToResultObject, $output);
        }

        if (($addToResultObject === true || is_array($addToResultObject)) && is_array($output)) {
            foreach ($output as $key => $value) {
                if ($addToResultObject === true) {
                    $result->set(is_string($key) ? $key : '', $value);
                } elseif (is_array($addToResultObject) && in_array($key, $addToResultObject, true)) {
                    $result->set($this->choseResultKey($key), $value);
                }
            }
        }

        return $result;
    }

    /**
     * When user defines an array of keys that shall be added to the result it can also contain a mapping.
     * If it does, use the key that it should be mapped to, instead of the key it has in the output array.
     */
    protected function choseResultKey(int|string $keyInOutput): string
    {
        if (is_array($this->addToResult)) {
            $mapToKey = array_search($keyInOutput, $this->addToResult, true);

            if (is_string($mapToKey)) {
                return $mapToKey;
            }
        }

        return is_string($keyInOutput) ? $keyInOutput : '';
    }
}
