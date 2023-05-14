<?php

namespace Crwlr\Crawler\Steps\Filters;

interface FilterInterface
{
    /**
     * When the value that will be evaluated is array or object, provide a key to use from that array/object.
     */
    public function useKey(string $key): static;

    /**
     * Shall return true if the $valueInQuestion should be kept or false when it should be filtered out.
     */
    public function evaluate(mixed $valueInQuestion): bool;

    public function addOr(FilterInterface $filter): void;

    public function getOr(): ?FilterInterface;

    public function negate(): NegatedFilter;
}
