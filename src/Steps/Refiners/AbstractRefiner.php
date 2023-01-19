<?php

namespace Crwlr\Crawler\Steps\Refiners;

use Psr\Log\LoggerInterface;

abstract class AbstractRefiner implements RefinerInterface
{
    protected ?LoggerInterface $logger = null;

    public function addLogger(LoggerInterface $logger): static
    {
        $this->logger = $logger;

        return $this;
    }

    protected function logTypeWarning(string $staticRefinerMethod, mixed $value): void
    {
        $this->logger?->warning(
            'Refiner ' . $staticRefinerMethod . ' can\'t be applied to value of type ' . gettype($value)
        );
    }
}
