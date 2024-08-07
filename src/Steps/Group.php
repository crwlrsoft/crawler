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
        $combinedOutput = [];

        if ($this->uniqueInput && !$this->inputOrOutputIsUnique($input)) {
            return;
        }

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
                        $combinedOutput = $this->addOutputToCombinedOutputs(
                            $output->get(),
                            $combinedOutput,
                            $nthOutput,
                        );
                    }
                }
            }

            yield from $this->prepareCombinedOutputs($combinedOutput, $input);
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
    private function addOutputToCombinedOutputs(
        mixed $output,
        array $combined,
        int $nthOutput,
    ): array {
        if (is_array($output)) {
            foreach ($output as $key => $value) {
                $combined[$nthOutput][$key][] = $value;
            }
        } else {
            $combined[$nthOutput][][] = $output;
        }

        return $combined;
    }

    /**
     * @param mixed[] $combinedOutputs
     * @param Input $input
     * @return Generator<Output>
     * @throws Exception
     */
    private function prepareCombinedOutputs(array $combinedOutputs, Input $input): Generator
    {
        foreach ($combinedOutputs as $combinedOutput) {
            $outputData = $this->normalizeCombinedOutputs($combinedOutput);

            $outputData = $this->applyRefiners($outputData, $input->get());

            if ($this->passesAllFilters($outputData)) {
                $output = $this->makeOutput($outputData, $input);

                if ($this->uniqueOutput !== false && !$this->inputOrOutputIsUnique($output)) {
                    continue;
                }

                yield $output;
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
