<?php

namespace Crwlr\Crawler\Steps;

use Crwlr\Crawler\Input;
use Crwlr\Crawler\Loader\LoaderInterface;
use Crwlr\Crawler\Output;
use Psr\Log\LoggerInterface;

abstract class Group implements GroupInterface
{
    /**
     * @var StepInterface[]
     */
    protected array $steps = [];

    protected LoggerInterface $logger;
    protected ?LoaderInterface $loader;

    abstract public static function new(): GroupInterface;

    /**
     * @return Output[]
     */
    abstract public function invokeStep(Input $input): array;

    public function addStep(StepInterface $step): self
    {
        $step->addLogger($this->logger);

        if (method_exists($step, 'addLoader')) {
            $step->addLoader($this->loader);
        }

        $this->steps[] = $step;

        return $this;
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
