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

    public function useInputKey(string $key): static;

    public function setResultKey(string $key): static;

    public function getResultKey(): ?string;

    /**
     * @param string[]|null $keys
     */
    public function addKeysToResult(?array $keys = null): static;

    public function addsKeysToResult(): bool;

    public function dontCascade(): static;

    public function cascades(): bool;

    public function addLogger(LoggerInterface $logger): static;
}
