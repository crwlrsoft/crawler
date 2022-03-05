<?php

namespace Crwlr\Crawler\Steps;

use Crwlr\Crawler\Input;
use Crwlr\Crawler\Loader\LoaderInterface;
use Crwlr\Crawler\Output;
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
    private bool $combine = false;

    /**
     * @param Input $input
     * @return Generator<Output>
     */
    public function invokeStep(Input $input): Generator
    {
        foreach ($this->steps as $step) {
            $outputs = $step->invokeStep($input);

            if (!$this->combine) {
                yield from $outputs;
            } else {
                // TODO
            }
        }
    }

    public function addStep(string|StepInterface $stepOrResultPropertyName, ?StepInterface $step = null): self
    {
        if (is_string($stepOrResultPropertyName) && $step === null) {
            throw new InvalidArgumentException('No StepInterface object provided');
        } elseif (is_string($stepOrResultPropertyName)) {
            $step->resultResourceProperty($stepOrResultPropertyName);
        } else {
            $step = $stepOrResultPropertyName;
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

    public function combineToSingleOutput(): self
    {
        $this->combine = true;

        return $this;
    }

    public function resultResourceProperty(string $propertyName): static
    {
        return $this;
    }

    public function resultDefined(): bool
    {
        foreach ($this->steps as $step) {
            if ($step->resultDefined()) {
                return true;
            }
        }

        return false;
    }
}
