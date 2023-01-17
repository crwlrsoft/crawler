<?php

namespace Crwlr\Crawler\Steps\Loading;

use Crwlr\Crawler\Loader\LoaderInterface;
use Crwlr\Crawler\Steps\StepInterface;

interface LoadingStepInterface extends StepInterface
{
    public function addLoader(LoaderInterface $loader): static;

    /**
     * Lets the user define which loader instance to use, if the crawler has multiple loaders.
     */
    public function useLoader(string $key): static;

    /**
     * Returns the key of the loader that should be used, if the user defined one, otherwise returns null.
     */
    public function usesLoader(): ?string;
}
