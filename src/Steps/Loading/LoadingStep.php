<?php

namespace Crwlr\Crawler\Steps\Loading;

use Crwlr\Crawler\Loader\LoaderInterface;

/**
 * @template T of LoaderInterface
 */

trait LoadingStep
{
    /**
     * @var T $loader
     */
    private LoaderInterface $loader;

    /**
     * @var ?T $customLoader
     */
    private ?LoaderInterface $customLoader = null;

    /**
     * @param T $loader
     */
    public function setLoader(LoaderInterface $loader): static
    {
        $this->loader = $loader;

        return $this;
    }

    /**
     * @param T $loader
     */
    public function withLoader(LoaderInterface $loader): static
    {
        $this->customLoader = $loader;

        return $this;
    }

    /**
     * @return T
     */
    protected function getLoader(): LoaderInterface
    {
        return $this->customLoader ?? $this->loader;
    }
}
