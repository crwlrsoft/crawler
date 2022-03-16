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

        if ($this->useInputKey !== null) {
            if (!array_key_exists($this->useInputKey, $input->get())) {
                throw new Exception('Key ' . $this->useInputKey . ' does not exist in input');
            }

            $input = new Input($input->get()[$this->useInputKey], $input->result);
        }

        // If in this group there are result keys and there is no Result object created yet, add one,
        // otherwise multiple Result object would be created.
        if ($this->combine && $this->anyResultKeysDefinedInSteps() && !$input->result) {
            $input = new Input($input->get(), new Result());
        }

        foreach ($this->steps as $key => $step) {
            $outputs = $step->invokeStep($input);

            if (!$this->combine) {
                if (method_exists($step, 'callUpdateInputUsingOutput')) {
                    foreach ($outputs as $output) {
                        $input = $step->callUpdateInputUsingOutput($input, $output);

                        if ($this->cascades() && $step->cascades()) {
                            yield $output;
                        }
                    }
                } elseif ($this->cascades() && $step->cascades()) {
                    yield from $outputs;
                }
            } else {
                foreach ($outputs as $output) {
                    if (method_exists($step, 'callUpdateInputUsingOutput')) {
                        $input = $step->callUpdateInputUsingOutput($input, $output);
                    }

                    if ($step->cascades()) {
                        $combinedOutput[$step->getResultKey() ?? $key][] = $output->get();
                    }
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

    private function anyResultKeysDefinedInSteps(): bool
    {
        foreach ($this->steps as $step) {
            if ($step->getResultKey() !== null) {
                return true;
            }
        }

        return false;
    }
}
