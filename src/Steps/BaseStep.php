<?php

namespace Crwlr\Crawler\Steps;

use Adbar\Dot;
use Closure;
use Crwlr\Crawler\Crawler;
use Crwlr\Crawler\Input;
use Crwlr\Crawler\Io;
use Crwlr\Crawler\Logger\PreStepInvocationLogger;
use Crwlr\Crawler\Output;
use Crwlr\Crawler\Result;
use Crwlr\Crawler\Steps\Exceptions\PreRunValidationException;
use Crwlr\Crawler\Steps\Filters\Filterable;
use Crwlr\Crawler\Steps\Refiners\RefinerInterface;
use Crwlr\Crawler\Utils\OutputTypeHelper;
use Generator;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;

/**
 * Base class for classes Step and Group which share some things in terms of adding output data to Result objects.
 */

abstract class BaseStep implements StepInterface
{
    use Filterable;

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
     * @var array<Closure|RefinerInterface|array{ key: string, refiner: Closure|RefinerInterface}>
     */
    protected array $refiners = [];

    protected ?string $outputKey = null;

    protected ?int $maxOutputs = null;

    protected int $currentOutputCount = 0;

    /**
     * @param Input $input
     * @return Generator<Output>
     */
    abstract public function invokeStep(Input $input): Generator;

    public function addLogger(LoggerInterface $logger): static
    {
        if ($this->logger instanceof PreStepInvocationLogger) {
            $this->logger->passToOtherLogger($logger);
        }

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

    public function refineOutput(
        string|Closure|RefinerInterface $keyOrRefiner,
        null|Closure|RefinerInterface $refiner = null,
    ): static {
        if ($refiner instanceof RefinerInterface && $this->logger) {
            $refiner->addLogger($this->logger);
        } elseif ($keyOrRefiner instanceof RefinerInterface && $this->logger) {
            $keyOrRefiner->addLogger($this->logger);
        }

        if (is_string($keyOrRefiner) && $refiner === null) {
            throw new InvalidArgumentException(
                'You have to provide a Refiner (Closure or instance of RefinerInterface)',
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

    public function maxOutputs(int $maxOutputs): static
    {
        $this->maxOutputs = $maxOutputs;

        return $this;
    }

    public function resetAfterRun(): void
    {
        $this->uniqueOutputKeys = $this->uniqueInputKeys = [];

        $this->currentOutputCount = 0;
    }

    /**
     * Define what type of outputs the step will yield
     *
     * Defining this in any step, helps to identify potential errors upfront when a crawler run is started.
     * If the step will only yield associative array (or object) outputs,
     * return StepOutputType::AssociativeArrayOrObject.
     * If it will only yield scalar (string, int, float, bool) outputs, return StepOutputType::Scalar.
     *
     * If it can potentially yield both types, but you can determine what it will yield, based on the state of the
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
                    'instead of keep()',
                );
            } elseif ($outputType === StepOutputType::Mixed) {
                $this->logger?->warning(
                    $this->getPreValidationRunMessageStartWithStepClassName() . ' potentially yields scalar value ' .
                    'outputs (= single string/int/bool/float with no key like in an associative array or object). ' .
                    'If it does (yield a scalar value output), it can not keep that output value, because it needs ' .
                    'a key for that. To avoid this, define a key for scalar outputs by using the keepAs() method.',
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
                    'array or object). Please define a key for the input data to keep, by using keepAs() instead.',
                );
            } elseif ($previousStepOutputType === StepOutputType::Mixed) {
                $this->logger?->warning(
                    $this->getPreValidationRunMessageStartWithStepClassName($previousStepOrInitialInputs) .
                    ' potentially yields scalar value outputs (= single string/int/bool/float with no key like in ' .
                    'an associative array or object). If it does (yield a scalar value output) the next step can not ' .
                    'keep it by using keepFromInput(). To avoid this, define a key for scalar inputs by using the ' .
                    'keepInputAs() method.',
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
                'string keys). The feature was called with an output of type ' . gettype($output->get()) . '.',
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
     * If you want to define aliases for certain output keys that can be used with keep(),
     * define this method in the child class and return the mappings.
     *
     * @return array<string, string>  alias => output key
     */
    protected function outputKeyAliases(): array
    {
        return [];
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
                        'use keepInputAs() instead with a key, that the input value should have in the kept data.',
                    );
                }
            }
        }
    }

    protected function getPreValidationRunMessageStartWithStepClassName(?BaseStep $step = null): string
    {
        $stepClassName = $this->getStepClassName($step);

        if ($stepClassName) {
            return 'The ' . $stepClassName . ' step';
        } else {
            $stepClassName = $this->getParentStepClassName($step);

            if (
                $stepClassName &&
                $stepClassName !== 'Crwlr\\Crawler\\Steps\\Step' &&
                $stepClassName !== 'Crwlr\\Crawler\\Steps\\BaseStep'
            ) {
                return 'An anonymous class step, that is extending the ' . $stepClassName . ' step';
            } else {
                return 'An anonymous class step';
            }
        }
    }

    protected function getStepClassName(?BaseStep $step = null): ?string
    {
        $stepClassName = get_class($step ?? $this);

        if (str_contains($stepClassName, '@anonymous')) {
            return null;
        }

        return $stepClassName;
    }

    protected function getParentStepClassName(?BaseStep $step = null): ?string
    {
        $parents = class_parents($step ?? $this);

        $firstLevelParent = reset($parents);

        if ($firstLevelParent && is_string($firstLevelParent) && !str_contains($firstLevelParent, '@anonymous')) {
            return $firstLevelParent;
        }

        return null;
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

    protected function makeOutput(mixed $outputData, Input $input): Output
    {
        $output = new Output(
            $outputData,
            $input->keep,
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
                    'output by using keepAs() instead of keep(). The value is now kept with an \'unnamed\' key.',
                );

                return [$this->nextUnnamedKey($alreadyKept) => $data];
            }

            $data = !is_array($data) ? OutputTypeHelper::objectToArray($data) : $data;

            if ($keepProperty === true) {
                return $data;
            } elseif (is_string($keepProperty)) {
                return [$keepProperty => $this->getOutputPropertyFromArray($keepProperty, $data)];
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
                $keepData[$value] = $this->getOutputPropertyFromArray($value, $data);
            } elseif (is_string($key)) {
                $keepData[$key] = $this->getOutputPropertyFromArray($value, $data);
            }
        }

        return $keepData;
    }

    /**
     * @param mixed[] $data
     */
    protected function getOutputPropertyFromArray(string $key, array $data): mixed
    {
        if (array_key_exists($key, $data)) {
            return $data[$key];
        } elseif ($this->isOutputKeyAlias($key)) {
            return $data[$this->getOutputKeyAliasRealKey($key)];
        }

        $data = $this->recursiveChildObjectsToArray($data);

        $dot = new Dot($data);

        return $dot->get($key);
    }

    /**
     * @param mixed[] $data
     * @return mixed[]
     */
    protected function recursiveChildObjectsToArray(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_object($value)) {
                $data[$key] = $this->recursiveChildObjectsToArray(OutputTypeHelper::objectToArray($value));
            } elseif (is_array($value)) {
                $data[$key] = $this->recursiveChildObjectsToArray($value);
            }
        }

        return $data;
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

    protected function maxOutputsExceeded(): bool
    {
        return $this->maxOutputs !== null && $this->currentOutputCount >= $this->maxOutputs;
    }

    protected function trackYieldedOutput(): void
    {
        if ($this->maxOutputs !== null) {
            $this->currentOutputCount += 1;
        }
    }
}
