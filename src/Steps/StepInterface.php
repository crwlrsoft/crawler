<?php

namespace Crwlr\Crawler\Steps;

use Crwlr\Crawler\Input;
use Psr\Log\LoggerInterface;

interface StepInterface
{
    public function invokeStep(Input $input): array;
    public function addLogger(LoggerInterface $logger);
}
