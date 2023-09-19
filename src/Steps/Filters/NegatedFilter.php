<?php

namespace Crwlr\Crawler\Steps\Filters;

final class NegatedFilter implements FilterInterface
{
    public function __construct(private readonly FilterInterface $filter) {}

    public function useKey(string $key): static
    {
        $this->filter->useKey($key);

        return $this;
    }

    public function evaluate(mixed $valueInQuestion): bool
    {
        return !$this->filter->evaluate($valueInQuestion);
    }

    public function addOr(FilterInterface $filter): void
    {
        $this->filter->addOr($filter);
    }

    public function getOr(): ?FilterInterface
    {
        return $this->filter->getOr();
    }

    public function negate(): NegatedFilter
    {
        return new NegatedFilter($this);
    }
}
