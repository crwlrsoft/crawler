<?php

namespace Crwlr\Crawler\Steps;

use Crwlr\Crawler\Input;
use Crwlr\Crawler\Output;
use Crwlr\Crawler\Steps\Filters\FilterInterface;
use Generator;
use Psr\Log\LoggerInterface;

interface StepInterface
{
    public function addLogger(LoggerInterface $logger): static;

    /**
     * @param Input $input
     * @return Generator<Output>
     */
    public function invokeStep(Input $input): Generator;

    public function useInputKey(string $key): static;

    /**
     * @param string|string[]|null $keys
     */
    public function addToResult(null|string|array $keys = null): static;

    /**
     * @param string|string[]|null $keys
     */
    public function addLaterToResult(null|string|array $keys = null): static;

    public function addsToOrCreatesResult(): bool;

    public function createsResult(): bool;

    public function uniqueInputs(?string $key = null): static;

    public function uniqueOutputs(?string $key = null): static;

    public function dontCascade(): static;

    public function cascades(): bool;

    public function where(string|FilterInterface $keyOrFilter, ?FilterInterface $filter = null): static;

    public function orWhere(string|FilterInterface $keyOrFilter, ?FilterInterface $filter = null): static;

    public function outputKey(string $key): static;

    public function keepInputData(?string $inputKey = null): static;

    public function resetAfterRun(): void;
}
