<?php

namespace Crwlr\Crawler\Steps\Refiners;

use Psr\Log\LoggerInterface;

interface RefinerInterface
{
    public function refine(mixed $value): mixed;

    public function addLogger(LoggerInterface $logger): static;
}
