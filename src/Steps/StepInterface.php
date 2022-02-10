<?php

namespace Crwlr\Crawler\Steps;

use Crwlr\Crawler\Input;
use Crwlr\Crawler\Output;
use Psr\Log\LoggerInterface;

interface StepInterface
{
    /**
     * @return Output[]
     */
    public function invokeStep(Input $input): array;
    public function addLogger(LoggerInterface $logger): static;
}
