<?php

namespace Crwlr\Crawler\Loader;

use Crwlr\Crawler\Exceptions\UnknownLoaderKeyException;
use Crwlr\Crawler\Steps\StepInterface;

class AddLoadersToStepAction
{
    /**
     * @param LoaderInterface|array<string, LoaderInterface> $loaders
     * @param StepInterface $step
     */
    public function __construct(protected LoaderInterface|array $loaders, protected StepInterface $step) {}

    /**
     * @return void
     * @throws UnknownLoaderKeyException
     */
    public function invoke(): void
    {
        if (!method_exists($this->step, 'addLoader')) {
            return;
        }

        if (is_array($this->loaders)) {
            $this->addLoadersToStep();
        } else {
            $this->step->addLoader($this->loaders);
        }
    }

    /**
     * Add either all or one of multiple defined loaders to the step
     *
     * A group step has a method addLoaders() to add all loaders at once and delegate them to child steps.
     * If user chose a specific loader for a step, add only that loader to the step.
     * Otherwise, call the steps addLoader() method with each loader one by one. In this case the step could implement
     * logic to decide which loader to accept.
     *
     * @throws UnknownLoaderKeyException
     */
    protected function addLoadersToStep(): void
    {
        if (!is_array($this->loaders) || !method_exists($this->step, 'addLoader')) {
            return;
        }

        if (method_exists($this->step, 'addLoaders')) {
            $this->step->addLoaders($this->loaders);
        } elseif (method_exists($this->step, 'usesLoader') && $this->step->usesLoader() !== null) {
            if (!isset($this->loaders[$this->step->usesLoader()])) {
                throw new UnknownLoaderKeyException();
            }

            $this->step->addLoader($this->loaders[$this->step->usesLoader()]);
        } else {
            foreach ($this->loaders as $loader) {
                $this->step->addLoader($loader);
            }
        }
    }
}
