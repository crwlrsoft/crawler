<?php

namespace Crwlr\Crawler\Steps;

use Crwlr\Crawler\Input;
use Crwlr\Crawler\Loader\LoaderInterface;
use Crwlr\Crawler\Output;
use Generator;
use Psr\Log\LoggerInterface;

final class Group implements StepInterface
{
    /**
     * @var StepInterface[]
     */
    protected array $steps = [];

    protected ?LoggerInterface $logger = null;
    protected ?LoaderInterface $loader = null;

    /**
     * @param Input $input
     * @return Generator<Output>
     */
    public function invokeStep(Input $input): Generator
    {
        foreach ($this->steps as $step) {
            yield from $step->invokeStep($input);
        }
    }

    public function addStep(StepInterface $step): self
    {
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
