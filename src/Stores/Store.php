<?php

namespace Crwlr\Crawler\Stores;

use Psr\Log\LoggerInterface;

abstract class Store implements StoreInterface
{
    protected ?LoggerInterface $logger = null;

    public function addLogger(LoggerInterface $logger): static
    {
        $this->logger = $logger;

        return $this;
    }
}
