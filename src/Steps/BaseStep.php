<?php

namespace Crwlr\Crawler\Steps;

use Adbar\Dot;
use Closure;
use Crwlr\Crawler\Crawler;
use Crwlr\Crawler\Input;
use Crwlr\Crawler\Io;
use Crwlr\Crawler\Output;
use Crwlr\Crawler\Result;
use Crwlr\Crawler\Steps\Exceptions\PreRunValidationException;
use Crwlr\Crawler\Steps\Filters\FilterInterface;
use Crwlr\Crawler\Steps\Refiners\RefinerInterface;
use Crwlr\Crawler\Utils\OutputTypeHelper;
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
     * true means: keep the whole output array/object
     * string: keep that one key from the (array/object) output
     * array: keep those keys from the (array/object) output
     *
     * @var bool|string|string[]
     */
    protected bool|string|array $keep = false;

    /**
     * Same as $keep, but for input data.
     *
     * @var bool|string|string[]
     */
    protected bool|string|array $keepFromInput = false;

    protected ?string $keepAs = null;

    protected ?string $keepInputAs = null;

    protected ?Crawler $parentCrawler = null;

    /**
     * @var array<string, Closure>
     */
    protected array $subCrawlers = [];

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

    public function setParentCrawler(Crawler $crawler): static
    {
        $this->parentCrawler = $crawler;

        return $this;
    }

    /**
     * @param string|string[]|null $keys
     */
    public function keep(string|array|null $keys = null): static
    {
        if ($keys === null) {
            $this->keep = true;
        } else {
            $this->keep = $keys;
        }

        return $this;
    }

    public function keepAs(string $key): static
    {
        $this->keepAs = $key;

        return $this;
    }

    /**
     * @param string|string[]|null $keys
     */
    public function keepFromInput(string|array|null $keys = null): static
    {
        if ($keys === null) {
            $this->keepFromInput = true;
        } else {
            $this->keepFromInput = $keys;
        }

        return $this;
    }

    public function keepInputAs(string $key): static
    {
        $this->keepInputAs = $key;

        return $this;
    }

    public function keepsAnything(): bool
    {
        return $this->keepsAnythingFromOutputData() || $this->keepsAnythingFromInputData();
    }

    public function keepsAnythingFromInputData(): bool
    {
        return $this->keepFromInput !== false || $this->keepInputAs !== null;
    }

    public function keepsAnythingFromOutputData(): bool
    {
        return $this->keep !== false || $this->keepAs !== null;
    }

    /**
     * @deprecated This method will be removed in v2 of the library. Please use the new keep() and keepAs()
     *             methods instead.
     */
    public function addToResult(array|string|null $keys = null): static
    {
        if (is_string($keys) || is_array($keys)) {
            $this->addToResult = $keys;
        } else {
            $this->addToResult = true;
        }

        return $this;
    }

    /**
     * @deprecated This method will be removed in v2 of the library. Please use the new keep() and keepAs()
     *             methods instead.
     */
    public function addLaterToResult(array|string|null $keys = null): static
    {
        if (is_string($keys) || is_array($keys)) {
            $this->addLaterToResult = $keys;
        } else {
            $this->addLaterToResult = true;
        }

        return $this;
    }

    /**
     * @deprecated Along with addToResult() and addLaterToResult() this method is also deprecated.
     */
    public function addsToOrCreatesResult(): bool
    {
        return $this->createsResult() || $this->addLaterToResult !== false;
    }

    /**
     * @deprecated Along with addToResult() this method is also deprecated.
     */
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
     * @deprecated This method will be removed in v2 of the library. Please use the new keepFromInput() or
     *             keepInputAs() methods instead.
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
     * Define what type of outputs the step will yield
     *
     * Defining this in any step, helps to identify potential errors upfront when a crawler run is started.
     * If the step will only yield associative array (or object) outputs,
     * return StepOutputType::AssociativeArrayOrObject.
     * If it will only yield scalar (string, int, float, bool) outputs, return StepOutputType::Scalar.
     *
     * If it can potentially yield both types, but you can determin what it will yield, based on the state of the
     * class, please implement this. Only if it can't be defined upfront, because it depends on the input, return
     * StepOutputType::Mixed.
     *
     * @return StepOutputType
     */
    public function outputType(): StepOutputType
    {
        return StepOutputType::Mixed;
    }

    /**
     * @param BaseStep|mixed[] $previousStepOrInitialInputs
     * @throws PreRunValidationException
     */
    public function validateBeforeRun(BaseStep|array $previousStepOrInitialInputs): void
    {
        if (!$previousStepOrInitialInputs instanceof BaseStep) {
            $this->validateFirstStepBeforeRun($previousStepOrInitialInputs);
        }

        if ($this->keep !== false && $this->keepAs === null && $this->outputKey === null) {
            $outputType = $this->outputType();

            if ($outputType === StepOutputType::Scalar) {
                throw new PreRunValidationException(
                    'Keeping data from a step that yields scalar value outputs (= single string/int/bool/float with ' .
                    'no key like in an associative array or object) requires to define a key, by using keepAs() ' .
                    'instead of keep()'
                );
            } elseif ($outputType === StepOutputType::Mixed) {
                $stepClassName = get_class($this);

                $this->logger?->warning(
                    'The ' . $stepClassName . ' step potentially yields scalar value outputs (= single ' .
                    'string/int/bool/float with no key like in an associative array or object). If it does (yield a ' .
                    'scalar value output), it can not keep that output value, because it needs a key for that. ' .
                    'To avoid this, define a key for scalar outputs by using the keepAs() method.'
                );
            }
        }

        if (
            $this->keepFromInput !== false &&
            $previousStepOrInitialInputs instanceof BaseStep &&
            $this->keepInputAs === null
        ) {
            $previousStepOutputType = $previousStepOrInitialInputs->outputType();

            if ($previousStepOutputType === StepOutputType::Scalar) {
                throw new PreRunValidationException(
                    'You are trying to keep data from a step\'s input with keepFromInput(), but the step before it ' .
                    'returns scalar value outputs (= single string/int/bool/float with no key like in an associative ' .
                    'array or object). Please define a key for the input data to keep, by using keepAs() instead.'
                );
            } elseif ($previousStepOutputType === StepOutputType::Mixed) {
                $stepClassName = get_class($this);

                $this->logger?->warning(
                    'The step before the ' . $stepClassName . ' step, potentially yields scalar value outputs ' .
                    '(= single string/int/bool/float with no key like in an associative array or object). If it does ' .
                    '(yield a scalar value output) the next step can not keep it by using keepFromInput(). To avoid ' .
                    'this, define a key for scalar inputs by using the keepInputAs() method.'
                );
            }
        }
    }

    public function subCrawlerFor(string $for, Closure $crawlerBuilder): static
    {
        $this->subCrawlers[$for] = $crawlerBuilder;

        return $this;
    }

    protected function runSubCrawlersFor(Output $output): Output
    {
        if (empty($this->subCrawlers)) {
            return $output;
        }

        if (!$output->isArrayWithStringKeys()) {
            $this->logger?->error(
                'The sub crawler feature works only with outputs that are associative arrays (arrays with ' .
                'string keys). The feature was called with an output of type ' . gettype($output->get()) . '.'
            );

            return $output;
        }

        if (!$this->parentCrawler) {
            $this->logger?->error('Can\'t make sub crawler, because the step has no reference to the parent crawler.');
        } else {
            foreach ($this->subCrawlers as $forKey => $crawlerBuilder) {
                $outputValue = $output->getProperty($forKey);

                if ($outputValue !== null) {
                    $crawler = $crawlerBuilder($this->parentCrawler->getSubCrawler());

                    is_array($outputValue) ? $crawler->inputs($outputValue) : $crawler->input($outputValue);

                    $results = [];

                    foreach ($crawler->run() as $result) {
                        $results[] = $result;
                    }

                    $resultCount = count($results);

                    if ($resultCount === 0) {
                        $output = $output->withPropertyValue($forKey, null);
                    } elseif ($resultCount === 1) {
                        $output = $output->withPropertyValue($forKey, $results[0]->toArray());
                    } else {
                        $output = $output->withPropertyValue(
                            $forKey,
                            array_map(function (Result $result) {
                                return $result->toArray();
                            }, $results),
                        );
                    }
                }
            }
        }

        return $output;
    }

    /**
     * @param mixed[] $initialInputs
     * @throws PreRunValidationException
     */
    protected function validateFirstStepBeforeRun(array $initialInputs): void
    {
        if ($initialInputs === []) {
            $this->logger?->error('You did not provide any initial inputs for your crawler.');

            return;
        }

        if ($this->keepFromInput !== false) {
            foreach ($initialInputs as $input) {
                if (!OutputTypeHelper::isAssociativeArrayOrObject($input)) {
                    throw new PreRunValidationException(
                        'The initial inputs contain scalar values (without keys) and you are calling keepFromInput() ' .
                        'on the first step (if not the first step in your whole crawler, check sub crawlers). Please ' .
                        'use keepInputAs() instead with a key, that the input value should have in the kept data.'
                    );
                }
            }
        }
    }

    protected function getInputKeyToUse(Input $input): ?Input
    {
        if ($this->useInputKey !== null) {
            $inputValue = $input->get();

            if (!is_array($inputValue) || !array_key_exists($this->useInputKey, $inputValue)) {
                if (!array_key_exists($this->useInputKey, $input->keep)) {
                    $warningMessage = '';

                    if (!is_array($inputValue)) {
                        $warningMessage = 'Can\'t get key from input, because input is of type ' .
                            gettype($inputValue) . ' instead of array.';
                    } elseif (!array_key_exists($this->useInputKey, $inputValue)) {
                        $warningMessage = 'Can\'t get key from input, because it does not exist.';
                    }

                    if (!empty($input->keep)) {
                        $warningMessage .= ' Key also is not present in data kept from previous steps.';
                    }

                    $this->logger?->warning($warningMessage);

                    return null;
                }

                $valueToUse = $input->keep[$this->useInputKey];
            } else {
                $valueToUse = $inputValue[$this->useInputKey];
            }

            $input = $input->withValue($valueToUse);
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
     * @deprecated because the keepInputData() feature is deprecated.
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
        $output = new Output(
            $outputData,
            $this->addOutputDataToResult($outputData, $input),
            $this->addOutputDataToAddLaterResult($outputData, $input),
            $input->keep
        );

        $output = $this->runSubCrawlersFor($output);

        $this->keepData($output, $input);

        return $output;
    }

    protected function keepData(Output $output, Input $input): void
    {
        if (!$this->keepsAnything()) {
            return;
        }

        if ($this->keepsAnythingFromInputData()) {
            $inputDataToKeep = $this->getInputDataToKeep($input, $output->keep);

            if (!empty($inputDataToKeep)) {
                $output->keep($inputDataToKeep);
            }
        }

        if ($this->keepsAnythingFromOutputData()) {
            $outputDataToKeep = $this->getOutputDataToKeep($output, $output->keep);

            if (!empty($outputDataToKeep)) {
                $output->keep($outputDataToKeep);
            }
        }
    }

    /**
     * @param array<string, mixed> $alreadyKept
     * @return mixed[]|null
     */
    protected function getOutputDataToKeep(Output $output, array $alreadyKept): ?array
    {
        return $this->getInputOrOutputDataToKeep($output, $alreadyKept);
    }

    /**
     * @param array<string, mixed> $alreadyKept
     * @return mixed[]|null
     */
    protected function getInputDataToKeep(Input $input, array $alreadyKept): ?array
    {
        return $this->getInputOrOutputDataToKeep($input, $alreadyKept);
    }

    /**
     * @param array<string, mixed> $alreadyKept
     * @return mixed[]|null
     */
    protected function getInputOrOutputDataToKeep(Io $io, array $alreadyKept): ?array
    {
        $keepProperty = $io instanceof Output ? $this->keep : $this->keepFromInput;

        $keepAsProperty = $io instanceof Output ? $this->keepAs : $this->keepInputAs;

        $data = $io->get();

        $isScalarValue = OutputTypeHelper::isScalar($data);

        if ($keepAsProperty !== null && ($isScalarValue || $keepProperty === false)) {
            return [$keepAsProperty => $data];
        } elseif ($keepProperty !== false) {
            if ($isScalarValue) {
                $variableMessagePart = $io instanceof Output ? 'yielded an output' : 'received an input';

                $this->logger?->error(
                    'A ' . get_class($this) . ' step ' . $variableMessagePart . ' that is neither an associative ' .
                    'array, nor an object, so there is no key for the value to keep. Please define a key for the ' .
                    'output by using keepAs() instead of keep(). The value is now kept with an \'unnamed\' key.'
                );

                return [$this->nextUnnamedKey($alreadyKept) => $data];
            }

            $data = !is_array($data) ? OutputTypeHelper::objectToArray($data) : $data;

            if ($keepProperty === true) {
                return $data;
            } elseif (is_string($keepProperty)) {
                return [$keepProperty => $data[$keepProperty] ?? null];
            }

            return $this->mapKeepProperties($data, $keepProperty);
        }

        return null;
    }

    /**
     * @param array<string, mixed> $data
     * @return string
     */
    protected function nextUnnamedKey(array $data): string
    {
        $i = 1;

        while (isset($data['unnamed' . $i])) {
            $i++;
        }

        return 'unnamed' . $i;
    }

    /**
     * @param mixed[] $data
     * @param array<int|string, string> $keep
     * @return mixed[]
     */
    protected function mapKeepProperties(array $data, array $keep): array
    {
        $keepData = [];

        foreach ($keep as $key => $value) {
            if (is_int($key)) {
                $keepData[$value] = $data[$value] ?? null;
            } elseif (is_string($key)) {
                $keepData[$key] = $data[$value] ?? null;
            }
        }

        return $keepData;
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
