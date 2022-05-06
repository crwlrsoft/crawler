<?php

namespace Crwlr\Crawler\Stores;

use Crwlr\Crawler\Result;
use Psr\Log\LoggerInterface;

interface StoreInterface
{
    public function store(Result $result): void;

    public function addLogger(LoggerInterface $logger): static;
}
