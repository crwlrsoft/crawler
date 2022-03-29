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

final class Group implements StepInterface
{
    /**
     * @var StepInterface[]
     */
    private array $steps = [];

    private ?LoggerInterface $logger = null;
    private ?LoaderInterface $loader = null;
    private bool $cascades = true;
    private bool $combine = false;
    private ?string $useInputKey = null;

    /**
     * @param Input $input
     * @return Generator<Output>
     * @throws Exception
     */
    public function invokeStep(Input $input): Generator
    {
        $combinedOutput = [];

        $input = $this->prepareInput($input);

        foreach ($this->steps as $key => $step) {
            foreach ($step->invokeStep($input) as $output) {
                if (method_exists($step, 'callUpdateInputUsingOutput')) {
                    $input = $step->callUpdateInputUsingOutput($input, $output);
                }

                if ($this->combine && $step->cascades()) {
                    $stepKey = $step->getResultKey() ?? $key;

                    $combinedOutput = $this->addOutputToCombinedOutputs($output, $combinedOutput, $stepKey);
                } elseif ($this->cascades() && $step->cascades()) {
                    yield $output;
                }
            }
        }

        if ($this->combine && $this->cascades()) {
            yield new Output(array_map(function ($output) {
                return count($output) === 1 ? reset($output) : $output;
            }, $combinedOutput), $input->result);
        }
    }

    public function useInputKey(string $key): static
    {
        $this->useInputKey = $key;

        return $this;
    }

    public function dontCascade(): static
    {
        $this->cascades = false;

        return $this;
    }

    public function cascades(): bool
    {
        return $this->cascades;
    }

    public function combineToSingleOutput(): self
    {
        $this->combine = true;

        return $this;
    }

    // TODO: what to do here? When setting a result key for a group, should all steps add their outputs to that one
    // property?
    public function setResultKey(string $key): static
    {
        return $this;
    }

    public function getResultKey(): ?string
    {
        return null;
    }

    public function addKeysToResult(?array $keys = null): static
    {
        return $this; // TODO: same here...should it try to add every output of the group?
    }

    public function addsKeysToResult(): bool
    {
        foreach ($this->steps as $step) {
            if ($step->addsKeysToResult()) {
                return true;
            }
        }

        return false;
    }

    public function addStep(string|StepInterface $stepOrResultKey, ?StepInterface $step = null): self
    {
        if (is_string($stepOrResultKey) && $step === null) {
            throw new InvalidArgumentException('No StepInterface object provided');
        } elseif (is_string($stepOrResultKey)) {
            $step->setResultKey($stepOrResultKey);
        } else {
            $step = $stepOrResultKey;
        }

        if ($this->logger instanceof LoggerInterface) {
            $step->addLogger($this->logger);
        }

        if (method_exists($step, 'addLoader') && $this->loader instanceof LoaderInterface) {
            $step->addLoader($this->loader);
        }

        $this->steps[] = $step;

        return $this;
    }

    public function addLogger(LoggerInterface $logger): static
    {
        $this->logger = $logger;

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
     * @throws Exception
     */
    private function getInputKeyToUse(Input $input): Input
    {
        if ($this->useInputKey !== null) {
            if (!array_key_exists($this->useInputKey, $input->get())) {
                throw new Exception('Key ' . $this->useInputKey . ' does not exist in input');
            }

            $input = new Input($input->get()[$this->useInputKey], $input->result);
        }

        return $input;
    }

    /**
     * If in this group there are result keys and there is no Result object created before invoking the steps,
     * add one, because otherwise multiple Result objects would be created.
     *
     * @param Input $input
     * @return Input
     */
    private function addResultToInputIfAnyResultKeysDefined(Input $input): Input
    {
        if ($this->combine && $this->addsKeysToResult() && !$input->result) {
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
                $combined[$stepKey][$key][] = $value;
            }
        } else {
            $combined[$stepKey][] = $output->get();
        }

        return $combined;
    }
}
