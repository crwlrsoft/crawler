<?php

namespace Crwlr\Crawler\Steps;

use Adbar\Dot;
use Closure;
use Crwlr\Crawler\Input;
use Crwlr\Crawler\Io;
use Crwlr\Crawler\Output;
use Crwlr\Crawler\Result;
use Crwlr\Crawler\Steps\Filters\FilterInterface;
use Crwlr\Crawler\Steps\Refiners\RefinerInterface;
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

    /**
     * @var array<Closure|RefinerInterface|array{ key: string, refiner: Closure|RefinerInterface}>
     */
    protected array $refiners = [];

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

    public function useInputKey(string $key): static
    {
        $this->useInputKey = $key;

        return $this;
    }

    public function uniqueInputs(?string $key = null): static
    {
        $this->uniqueInput = $key ?? true;

        return $this;
    }

    public function uniqueOutputs(?string $key = null): static
    {
        $this->uniqueOutput = $key ?? true;

        return $this;
    }

    public function where(string|FilterInterface $keyOrFilter, ?FilterInterface $filter = null): static
    {
        if (is_string($keyOrFilter) && $filter === null) {
            throw new InvalidArgumentException('You have to provide a Filter (instance of FilterInterface)');
        } elseif (is_string($keyOrFilter)) {
            if ($this->isOutputKeyAlias($keyOrFilter)) {
                $keyOrFilter = $this->getOutputKeyAliasRealKey($keyOrFilter);
            }

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
    public function orWhere(string|FilterInterface $keyOrFilter, ?FilterInterface $filter = null): static
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

    public function refineOutput(
        string|Closure|RefinerInterface $keyOrRefiner,
        null|Closure|RefinerInterface $refiner = null
    ): static {
        if ($refiner instanceof RefinerInterface && $this->logger) {
            $refiner->addLogger($this->logger);
        } elseif ($keyOrRefiner instanceof RefinerInterface && $this->logger) {
            $keyOrRefiner->addLogger($this->logger);
        }

        if (is_string($keyOrRefiner) && $refiner === null) {
            throw new InvalidArgumentException(
                'You have to provide a Refiner (Closure or instance of RefinerInterface)'
            );
        } elseif (is_string($keyOrRefiner)) {
            $this->refiners[] = ['key' => $keyOrRefiner, 'refiner' => $refiner];
        } else {
            $this->refiners[] = $keyOrRefiner;
        }

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

    protected function getInputKeyToUse(Input $input): ?Input
    {
        if ($this->useInputKey !== null) {
            $inputValue = $input->get();

            if (!is_array($inputValue)) {
                $this->logger?->warning(
                    'Can\'t get key from input, because input is of type ' . gettype($inputValue) . ' instead of ' .
                    'array.'
                );

                return null;
            } elseif (!array_key_exists($this->useInputKey, $inputValue)) {
                $this->logger?->warning('Can\'t get key from input, because it does not exist.');

                return null;
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

    protected function applyRefiners(mixed $outputValue, mixed $inputValue): mixed
    {
        foreach ($this->refiners as $refiner) {
            $outputValueToRefine = $outputValue;

            if (is_array($refiner) && isset($outputValue[$refiner['key']])) {
                $outputValueToRefine = $outputValue[$refiner['key']];
            }

            if ($refiner instanceof Closure) {
                $refinedOutputValue = $refiner->call($this, $outputValueToRefine, $inputValue);
            } elseif ($refiner instanceof RefinerInterface) {
                $refinedOutputValue = $refiner->refine($outputValueToRefine);
            } else {
                if ($refiner['refiner'] instanceof Closure) {
                    $refinedOutputValue = $refiner['refiner']->call($this, $outputValueToRefine, $inputValue);
                } else {
                    $refinedOutputValue = $refiner['refiner']->refine($outputValueToRefine);
                }
            }

            if (is_array($refiner) && isset($outputValue[$refiner['key']])) {
                $outputValue[$refiner['key']] = $refinedOutputValue;
            } else {
                $outputValue = $refinedOutputValue;
            }
        }

        return $outputValue;
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

        if (
            ($addToResultObject === true || is_array($addToResultObject)) &&
            (is_array($output) || (is_object($output) && method_exists($output, '__serialize')))
        ) {
            if (!is_array($output)) {
                $output = $output->__serialize();
            }

            $output = $this->serializeElementsOfOutputArray($output);

            foreach ($output as $key => $value) {
                if ($addToResultObject === true) {
                    $result->set(is_string($key) ? $key : '', $value);
                } elseif ($this->shouldBeAdded($key, $addToResultObject)) {
                    $result->set($this->choseResultKey($key), $value);
                }
            }

            if (is_array($addToResultObject)) {
                $this->tryToAddMissingKeysUsingDotNotation($output, $addToResultObject, $result);
            }
        }

        return $result;
    }

    /**
     * @param mixed[] $output
     * @return mixed[]
     */
    protected function serializeElementsOfOutputArray(array $output): array
    {
        foreach ($output as $key => $value) {
            if (is_object($value)) {
                if (method_exists($value, 'toArrayForAddToResult')) {
                    $output[$key] = $value->toArrayForAddToResult();
                } elseif (method_exists($value, '__serialize')) {
                    $output[$key] = $value->__serialize();
                }
            }

            if (is_array($value)) {
                $output[$key] = $this->serializeElementsOfOutputArray($value);
            }
        }

        return $output;
    }

    /**
     * When user defines an array of keys that shall be added to the result it can also contain a mapping.
     * If it does, use the key that it should be mapped to, instead of the key it has in the output array.
     */
    protected function choseResultKey(int|string $keyInOutput): string
    {
        if (is_string($keyInOutput)) {
            if (is_array($this->addToResult)) {
                if (in_array($keyInOutput, $this->addToResult, true)) {
                    $mapTo = array_search($keyInOutput, $this->addToResult, true);

                    return is_string($mapTo) ? $mapTo : $keyInOutput;
                }

                foreach ($this->getAliasesForOutputKey($keyInOutput) as $alias) {
                    if (in_array($alias, $this->addToResult, true)) {
                        $mapTo = array_search($alias, $this->addToResult, true);

                        return is_string($mapTo) ? $mapTo : $alias;
                    }
                }
            } elseif (is_bool($this->addToResult)) {
                return $keyInOutput;
            }
        } elseif (is_string($this->addToResult)) {
            return $this->addToResult;
        }

        return '';
    }

    /**
     * @param mixed[] $output
     * @param array<int|string, string> $addToResult
     */
    protected function tryToAddMissingKeysUsingDotNotation(array $output, array $addToResult, Result $result): void
    {
        $outputDot = new Dot($output);

        foreach ($addToResult as $resultKeyOrInt => $potentialDotNotationKey) {
            $resultKey = is_int($resultKeyOrInt) ? $potentialDotNotationKey : $resultKeyOrInt;

            if ($result->get($resultKey) === null) {
                $valueUsingDotNotation = $outputDot->get($potentialDotNotationKey);

                if ($valueUsingDotNotation !== null) {
                    $result->set($resultKey, $valueUsingDotNotation);
                }
            }
        }
    }

    /**
     * If you want to define aliases for certain output keys that can be used with addToResult, define this method in
     * the child class and return the mappings.
     *
     * @return array<string, string>  alias => output key
     */
    protected function outputKeyAliases(): array
    {
        return [];
    }

    /**
     * @param string $key
     * @return string[]
     */
    protected function getAliasesForOutputKey(string $key): array
    {
        $aliases = [];

        foreach ($this->outputKeyAliases() as $alias => $outputKey) {
            if ($outputKey === $key) {
                $aliases[] = $alias;
            }
        }

        return $aliases;
    }

    protected function isOutputKeyAlias(string $key): bool
    {
        return array_key_exists($key, $this->outputKeyAliases());
    }

    protected function getOutputKeyAliasRealKey(string $key): string
    {
        $mapping = $this->outputKeyAliases();

        return $mapping[$key];
    }

    /**
     * @param string $key
     * @param string[] $addToResultKeys
     * @return bool
     */
    protected function shouldBeAdded(string $key, array $addToResultKeys): bool
    {
        if (in_array($key, $addToResultKeys, true)) {
            return true;
        }

        foreach ($this->getAliasesForOutputKey($key) as $alias) {
            if (in_array($alias, $addToResultKeys, true)) {
                return true;
            }
        }

        return false;
    }
}
