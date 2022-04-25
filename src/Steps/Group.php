<?php

namespace Crwlr\Crawler\Steps;

use Crwlr\Crawler\Input;
use Crwlr\Crawler\Loader\LoaderInterface;
use Crwlr\Crawler\Output;
use Crwlr\Crawler\Result;
use Exception;
use Generator;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;

final class Group extends BaseStep implements StepInterface
{
    /**
     * @var StepInterface[]
     */
    private array $steps = [];

    private ?LoaderInterface $loader = null;

    private bool $combine = false;

    /**
     * @param Input $input
     * @return Generator<Output>
     * @throws Exception
     */
    public function invokeStep(Input $input): Generator
    {
        $combinedOutput = $uniqueKeys = [];

        $input = $this->prepareInput($input);

        foreach ($this->steps as $key => $step) {
            foreach ($step->invokeStep($input) as $output) {
                if (method_exists($step, 'callUpdateInputUsingOutput')) {
                    $input = $step->callUpdateInputUsingOutput($input, $output);
                }

                if ($this->combine && $step->cascades()) {
                    $stepKey = $step->getResultKey() ?? $key;

                    $combinedOutput = $this->addOutputToCombinedOutputs($output->get(), $combinedOutput, $stepKey);
                } elseif ($this->cascades() && $step->cascades()) {
                    if ($this->uniqueOutput !== false && $this->existsInUniqueKeys($output, $uniqueKeys)) {
                        continue;
                    }

                    yield $output;
                }
            }
        }

        if ($this->combine && $this->cascades()) {
            yield $this->prepareCombinedOutputs($combinedOutput, $input->result);
        }
    }

    public function combineToSingleOutput(): self
    {
        $this->combine = true;

        return $this;
    }

    /**
     * @throws Exception
     */
    public function setResultKey(string $key): static
    {
        if (!$this->combine) {
            throw new Exception('Groups can only add data to results when output is combined to a single output.');
        }

        return parent::setResultKey($key);
    }

    /**
     * @throws Exception
     */
    public function addKeysToResult(?array $keys = null): static
    {
        if (!$this->combine) {
            throw new Exception('Groups can only add data to results when output is combined to a single output.');
        }

        return parent::addKeysToResult($keys);
    }

    public function addsToOrCreatesResult(): bool
    {
        if (parent::addsToOrCreatesResult()) {
            return true;
        }

        foreach ($this->steps as $step) {
            if ($step->addsToOrCreatesResult()) {
                return true;
            }
        }

        return false;
    }

    public function addStep(string|StepInterface $stepOrResultKey, ?StepInterface $step = null): self
    {
        if (is_string($stepOrResultKey) && $step === null) {
            throw new InvalidArgumentException('No StepInterface object provided');
        } elseif ($stepOrResultKey instanceof StepInterface) {
            $step = $stepOrResultKey;
        }

        if ($this->logger instanceof LoggerInterface) {
            $step->addLogger($this->logger);
        }

        if (method_exists($step, 'addLoader') && $this->loader instanceof LoaderInterface) {
            $step->addLoader($this->loader);
        }

        if (is_string($stepOrResultKey) && !isset($this->steps[$stepOrResultKey])) {
            $this->steps[$stepOrResultKey] = $step;
        } else {
            $this->steps[] = $step;
        }

        return $this;
    }

    public function addLogger(LoggerInterface $logger): static
    {
        parent::addLogger($logger);

        foreach ($this->steps as $step) {
            $step->addLogger($logger);
        }

        return $this;
    }

    public function addLoader(LoaderInterface $loader): self
    {
        $this->loader = $loader;

        foreach ($this->steps as $step) {
            if (method_exists($step, 'addLoader')) {
                $step->addLoader($loader);
            }
        }

        return $this;
    }

    /**
     * @throws Exception
     */
    private function prepareInput(Input $input): Input
    {
        $input = $this->getInputKeyToUse($input);

        $input = $this->addResultToInputIfAnyResultKeysDefined($input);

        return $input;
    }

    /**
     * If this group combines the output, there are result keys and there is no Result object created before invoking
     * the steps, add one. Because otherwise multiple Result objects will be created.
     *
     * @param Input $input
     * @return Input
     */
    private function addResultToInputIfAnyResultKeysDefined(Input $input): Input
    {
        if ($this->combine && $this->addsToOrCreatesResult() && !$input->result) {
            $input = new Input($input->get(), new Result());
        }

        return $input;
    }

    /**
     * @param mixed[] $combined
     * @return mixed[]
     */
    private function addOutputToCombinedOutputs(mixed $output, array $combined, int|string $stepKey): array
    {
        if (is_array($output)) {
            foreach ($output as $key => $value) {
                if (is_int($stepKey) && is_string($key)) {
                    $combined[$key][] = $value;
                } else {
                    $combined[$stepKey][$key][] = $value;
                }
            }
        } else {
            $combined[$stepKey][] = $output;
        }

        return $combined;
    }

    /**
     * @param mixed[] $combinedOutputs
     * @param Result|null $result
     * @return Output
     */
    private function prepareCombinedOutputs(array $combinedOutputs, ?Result $result = null): Output
    {
        $outputData = $this->normalizeCombinedOutputs($combinedOutputs, $result);

        $this->addOutputDataToResult($outputData, $result);

        return new Output($outputData, $result);
    }

    /**
     * Normalize combined outputs
     *
     * When adding outputs to combined output during step invocation, it always adds as arrays.
     * Here it unwraps all array properties with just one element to have just that one element as value.
     *
     * @param mixed[] $combinedOutputs
     * @return mixed[]
     */
    private function normalizeCombinedOutputs(array $combinedOutputs, ?Result $result = null): array
    {
        $normalized = [];

        foreach ($combinedOutputs as $key => $combinedOutput) {
            if (count($combinedOutput) === 1) {
                $normalized[$key] = reset($combinedOutput);
            } else {
                $normalized[$key] = $combinedOutput;
            }
        }

        return $normalized;
    }

    /**
     * @param bool[] $uniqueKeys
     */
    private function existsInUniqueKeys(Output $output, array &$uniqueKeys): bool
    {
        $uniqueKey = $output->setKey(is_string($this->uniqueOutput) ? $this->uniqueOutput : null);

        if (isset($uniqueKeys[$uniqueKey])) {
            return true;
        }

        $uniqueKeys[$uniqueKey] = true;

        return false;
    }
}
