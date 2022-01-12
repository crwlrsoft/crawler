<?php

namespace Crwlr\Crawler\Steps\Loading;

use Crwlr\Crawler\Loader\LoaderInterface;
use Crwlr\Crawler\Steps\StepInterface;

interface LoadingStepInterface extends StepInterface
{
    public function addLoader(LoaderInterface $loader);
}
