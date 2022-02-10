<?php

namespace Crwlr\Crawler\Steps;

use Crwlr\Crawler\Loader\LoaderInterface;
use Psr\Log\LoggerInterface;

interface GroupInterface extends StepInterface
{
    public function addStep(StepInterface $step): self;
    public function addLogger(LoggerInterface $logger): self;
    public function addLoader(LoaderInterface $loader): self;
}
