<?php

namespace Crwlr\Crawler\Steps;

use Crwlr\Crawler\Input;
use Crwlr\Crawler\Output;
use Generator;
use Psr\Log\LoggerInterface;

interface StepInterface
{
    /**
     * @param Input $input
     * @return Generator<Output>
     */
    public function invokeStep(Input $input): Generator;
    public function addLogger(LoggerInterface $logger): static;
    public function resultDefined(): bool;
}
