<?php

namespace Crwlr\Crawler\Steps;

use Crwlr\Crawler\Loader\LoaderInterface;

interface GroupInterface extends StepInterface
{
    public function addStep(StepInterface $step): self;
    public function addLoader(LoaderInterface $loader): self;
}
