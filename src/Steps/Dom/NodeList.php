<?php

namespace Crwlr\Crawler\Steps\Dom;

use ArrayIterator;
use Closure;
use Countable;
use Dom\Element;
use DOMNode;
use Exception;
use Iterator;
use IteratorAggregate;
use Symfony\Component\DomCrawler\Crawler;

/**
 * @implements IteratorAggregate<int, Node>
 */

class NodeList implements IteratorAggregate, Countable
{
    /**
     * @param \Dom\NodeList<\Dom\Node>|\Dom\NodeList<Element>|Crawler|array<Node> $nodeList
     */
    public function __construct(
        private readonly object|array $nodeList,
        private readonly ?Closure $makeNodeInstance = null,
    ) {}

    /**
     * @throws Exception
     */
    public function first(): ?Node
    {
        $iterator = $this->getIterator();

        $iterator->rewind();

        return $iterator->current();
    }

    /**
     * @throws Exception
     */
    public function last(): ?Node
    {
        $iterator = $this->getIterator();

        foreach ($iterator as $node) {
        }

        return $node ?? null;
    }

    /**
     * @throws Exception
     */
    public function nth(int $index): ?Node
    {
        $iterator = $this->getIterator();

        $i = 0;

        foreach ($iterator as $node) {
            if (($i + 1) === $index) {
                return $node;
            }

            $i++;
        }

        return null;
    }

    /**
     * @return mixed[]
     * @throws Exception
     */
    public function each(Closure $callback): array
    {
        $data = [];

        foreach ($this->getIterator() as $key => $node) {
            $data[] = $callback($node, $key);
        }

        return $data;
    }

    /**
     * @return int<0, max>
     */
    public function count(): int
    {
        if (is_array($this->nodeList)) {
            return count($this->nodeList);
        }

        return max(0, $this->nodeList->count());
    }

    public function getIterator(): Iterator
    {
        if (is_array($this->nodeList)) {
            return new ArrayIterator($this->nodeList);
        }

        $iterator = $this->nodeList->getIterator();

        /** @var Iterator<int, DOMNode|\Dom\Node> $iterator */

        return new class ($iterator, $this->makeNodeInstance) implements Iterator {
            /**
             * @param Iterator<int, DOMNode|\Dom\Node> $iterator
             */
            public function __construct(
                private readonly Iterator $iterator,
                private readonly ?Closure $makeNodeInstanceCallback = null,
            ) {}

            public function current(): ?Node
            {
                return $this->makeNodeInstance($this->iterator->current());
            }

            public function next(): void
            {
                $this->iterator->next();
            }

            public function key(): mixed
            {
                return $this->iterator->key();
            }

            public function valid(): bool
            {
                return $this->iterator->valid();
            }

            public function rewind(): void
            {
                $this->iterator->rewind();
            }

            /**
             * @param \Dom\Node|DOMNode|Crawler $node
             */
            private function makeNodeInstance(mixed $node): ?Node
            {
                if (!is_object($node)) { // @phpstan-ignore-line change when min. required PHP version is 8.4.
                    return null;
                }

                return $this->makeNodeInstanceCallback?->__invoke($node) ?? null;
            }
        };
    }
}
