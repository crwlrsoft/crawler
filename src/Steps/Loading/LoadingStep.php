<?php

namespace Crwlr\Crawler\Steps\Loading;

use Crwlr\Crawler\Loader\LoaderInterface;

trait LoadingStep
{
    private LoaderInterface $loader;

    private ?LoaderInterface $customLoader = null;

    public function setLoader(LoaderInterface $loader): static
    {
        $this->loader = $loader;

        return $this;
    }

    public function withLoader(LoaderInterface $loader): static
    {
        $this->customLoader = $loader;

        return $this;
    }

    protected function getLoader(): LoaderInterface
    {
        return $this->customLoader ?? $this->loader;
    }
}
