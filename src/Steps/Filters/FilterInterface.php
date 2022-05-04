<?php

namespace Crwlr\Crawler\Steps\Filters;

interface FilterInterface
{
    public function useKey(string $key): static;

    /**
     * Shall return true if the $valueInQuestion should be kept or false when it should be filtered out.
     */
    public function evaluate(mixed $valueInQuestion): bool;
}
