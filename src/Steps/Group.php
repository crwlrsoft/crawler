<?php

namespace Crwlr\Crawler\Steps;

use Crwlr\Crawler\Input;
use Crwlr\Crawler\Loader\LoaderInterface;
use Crwlr\Crawler\Output;
use Exception;
use Generator;
use Psr\Log\LoggerInterface;

final class Group extends BaseStep
{
    /**
     * @var StepInterface[]
     */
    private array $steps = [];

    /**
     * @var LoaderInterface|null
     */
    private ?LoaderInterface $loader = null;

    /**
     * @param Input $input
     * @return Generator<Output>
     * @throws Exception
     */
    public function invokeStep(Input $input): Generator
    {
        $combinedOutput = $combinedKeptData = [];

        if ($this->uniqueInput && !$this->inputOrOutputIsUnique($input)) {
            return;
        }

        $this->storeOriginalInput($input);

        // When input is array and useInputKey() was used, invoke the steps only with that input array element,
        // but keep the original input, because we want to use it e.g. for the keepInputData() functionality.
        $inputForStepInvocation = $this->getInputKeyToUse($input);

        if ($inputForStepInvocation) {
            foreach ($this->steps as $step) {
                foreach ($step->invokeStep($inputForStepInvocation) as $nthOutput => $output) {
                    if (method_exists($step, 'callUpdateInputUsingOutput')) {
                        $inputForStepInvocation = $step->callUpdateInputUsingOutput($inputForStepInvocation, $output);
                    }

                    if ($this->includeOutput($step)) {
                        $combinedOutput = $this->addToCombinedOutputData(
                            $output->get(),
                            $combinedOutput,
                            $nthOutput,
                        );
                    }

                    // Also transfer data, kept in group child steps, to the kept data of the final group output.
                    if ($output->keep !== $inputForStepInvocation->keep) {
                        $keep = $this->getNewlyKeptData($output, $inputForStepInvocation);

                        $combinedKeptData = $this->addToCombinedOutputData($keep, $combinedKeptData, $nthOutput);
                    }
                }
            }

            yield from $this->prepareCombinedOutputs($combinedOutput, $combinedKeptData, $input);
        }
    }

    public function addStep(StepInterface $step): self
    {
        if ($this->logger instanceof LoggerInterface) {
            $step->addLogger($this->logger);
        }

        if (method_exists($step, 'setLoader') && $this->loader instanceof LoaderInterface) {
            $step->setLoader($this->loader);
        }

        if ($this->maxOutputs) {
            $step->maxOutputs($this->maxOutputs);
        }

        $this->steps[] = $step;

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

    public function setLoader(LoaderInterface $loader): self
    {
        $this->loader = $loader;

        foreach ($this->steps as $step) {
            if (method_exists($step, 'setLoader')) {
                $step->setLoader($loader);
            }
        }

        return $this;
    }

    public function maxOutputs(int $maxOutputs): static
    {
        parent::maxOutputs($maxOutputs);

        foreach ($this->steps as $step) {
            $step->maxOutputs($maxOutputs);
        }

        return $this;
    }

    public function outputType(): StepOutputType
    {
        return StepOutputType::AssociativeArrayOrObject;
    }

    protected function includeOutput(StepInterface $step): bool
    {
        if (
            !method_exists($step, 'shouldOutputBeExcludedFromGroupOutput') ||
            $step->shouldOutputBeExcludedFromGroupOutput() === false
        ) {
            return true;
        }

        return false;
    }

    /**
     * @param mixed[] $combined
     * @return mixed[]
     */
    private function addToCombinedOutputData(mixed $add, array $combined, int $nthElement): array
    {
        if (is_array($add)) {
            foreach ($add as $key => $value) {
                $combined[$nthElement][$key][] = $value;
            }
        } else {
            $combined[$nthElement][][] = $add;
        }

        return $combined;
    }

    /**
     * @return mixed[]
     */
    private function getNewlyKeptData(Output $output, Input $input): array
    {
        return array_filter($output->keep, function ($key) use ($input) {
            return !array_key_exists($key, $input->keep);
        }, ARRAY_FILTER_USE_KEY);
    }

    /**
     * @param mixed[] $combinedOutputs
     * @param mixed[] $combinedKeptData
     * @param Input $input
     * @return Generator<Output>
     * @throws Exception
     */
    private function prepareCombinedOutputs(array $combinedOutputs, array $combinedKeptData, Input $input): Generator
    {
        foreach ($combinedOutputs as $key => $combinedOutput) {
            if ($this->maxOutputsExceeded()) {
                break;
            }

            $outputData = $this->normalizeCombinedOutputs($combinedOutput);

            $outputData = $this->applyRefiners($outputData, $input->get());

            if ($this->passesAllFilters($outputData)) {
                $output = $this->makeOutput($outputData, $input);

                if (array_key_exists($key, $combinedKeptData)) {
                    $output->keep($this->normalizeCombinedOutputs($combinedKeptData[$key]));
                }

                if ($this->uniqueOutput !== false && !$this->inputOrOutputIsUnique($output)) {
                    continue;
                }

                yield $output;

                $this->trackYieldedOutput();
            }
        }
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
    private function normalizeCombinedOutputs(array $combinedOutputs): array
    {
        return array_map(function ($output) {
            return count($output) === 1 ? reset($output) : $output;
        }, $combinedOutputs);
    }
}
