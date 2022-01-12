<?php

namespace Crwlr\Crawler;

abstract class Collection
{
    public function __construct(protected array $items)
    {
    }

    public function all(): array
    {
        return $this->items;
    }

    public function current(): mixed
    {
        return current($this->items);
    }

    public function next(): mixed
    {
        return next($this->items);
    }

    public function key(): int|string
    {
        return key($this->items);
    }

    public function valid(): bool
    {
        return key($this->items) !== null;
    }

    public function rewind(): void
    {
        reset($this->items);
    }
}
