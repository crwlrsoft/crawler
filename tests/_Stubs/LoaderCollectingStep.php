<?php

namespace tests\_Stubs;

use Crwlr\Crawler\Loader\LoaderInterface;
use Crwlr\Crawler\Steps\Loading\LoadingStep;
use Generator;

class LoaderCollectingStep extends LoadingStep
{
    /**
     * @var LoaderInterface[]
     */
    public array $loaders = [];

    public function addLoader(LoaderInterface $loader): static
    {
        $this->loaders[] = $loader;

        $this->loader = $loader;

        return $this;
    }

    protected function invoke(mixed $input): Generator
    {
        yield 'foo';
    }
}
