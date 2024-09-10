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

    /**
     * @param string|string[]|null $keys
     */
    public function keep(string|array|null $keys = null): static;

    public function keepAs(string $key): static;

    /**
     * @param string|string[]|null $keys
     */
    public function keepFromInput(string|array|null $keys = null): static;

    public function keepInputAs(string $key): static;

    public function keepsAnything(): bool;

    public function keepsAnythingFromInputData(): bool;

    public function keepsAnythingFromOutputData(): bool;

    public function useInputKey(string $key): static;

    public function uniqueInputs(?string $key = null): static;

    public function uniqueOutputs(?string $key = null): static;

    public function where(string|FilterInterface $keyOrFilter, ?FilterInterface $filter = null): static;

    public function orWhere(string|FilterInterface $keyOrFilter, ?FilterInterface $filter = null): static;

    public function outputKey(string $key): static;

    public function maxOutputs(int $maxOutputs): static;

    public function resetAfterRun(): void;
}
