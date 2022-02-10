<?php

namespace Crwlr\Crawler;

use Iterator;

/**
 * @implements Iterator<mixed>
 */

abstract class Collection implements Iterator
{
    /**
     * @param mixed[] $items
     */
    public function __construct(protected array $items)
    {
    }

    /**
     * @return mixed[]
     */
    public function all(): array
    {
        return $this->items;
    }

    public function current(): mixed
    {
        return current($this->items);
    }

    public function next(): void
    {
        next($this->items);
    }

    public function key(): int|string|null
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
