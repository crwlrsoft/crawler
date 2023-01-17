<?php

namespace Crwlr\Crawler\Steps\Loading;

use Crwlr\Crawler\Loader\LoaderInterface;
use Crwlr\Crawler\Steps\Step;

abstract class LoadingStep extends Step implements LoadingStepInterface
{
    protected LoaderInterface $loader;

    protected ?string $useLoaderKey = null;

    public function addLoader(LoaderInterface $loader): static
    {
        $this->loader = $loader;

        return $this;
    }

    public function useLoader(string $key): static
    {
        $this->useLoaderKey = $key;

        return $this;
    }

    public function usesLoader(): ?string
    {
        return $this->useLoaderKey;
    }
}
