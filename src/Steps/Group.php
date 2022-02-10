<?php

namespace Crwlr\Crawler\Steps;

use Crwlr\Crawler\Input;
use Crwlr\Crawler\Loader\LoaderInterface;
use Crwlr\Crawler\Output;
use Psr\Log\LoggerInterface;

final class Group implements GroupInterface
{
    /**
     * @var StepInterface[]
     */
    private array $steps = [];

    protected LoggerInterface $logger;
    protected ?LoaderInterface $loader;

    public static function new(): self
    {
        return new self();
    }

    public function addStep(StepInterface $step): self
    {
        $step->addLogger($this->logger);

        if (method_exists($step, 'addLoader')) {
            $step->addLoader($this->loader);
        }

        $this->steps[] = $step;

        return $this;
    }

    /**
     * @param Input $input
     * @return Output[]
     */
    public function invokeStep(Input $input): array
    {
        $outputs = [];

        foreach ($this->steps as $step) {
            array_push($outputs, ...$step->invokeStep($input));
        }

        return $outputs;
    }

    public function addLogger(LoggerInterface $logger): static
    {
        $this->logger = $logger;

        return $this;
    }

    public function addLoader(LoaderInterface $loader): self
    {
        $this->loader = $loader;

        return $this;
    }
}
