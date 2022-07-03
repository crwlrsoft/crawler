<?php

namespace Crwlr\Crawler\Steps;

use Crwlr\Crawler\Steps\Html\CssSelector;
use Crwlr\Crawler\Steps\Html\DomQueryInterface;
use Crwlr\Crawler\Steps\Html\XPathQuery;
use Exception;
use Generator;
use InvalidArgumentException;
use Symfony\Component\DomCrawler\Crawler;

abstract class Dom extends Step
{
    protected bool $root = false;

    protected ?DomQueryInterface $each = null;

    protected ?DomQueryInterface $first = null;

    protected ?DomQueryInterface $last = null;

    /**
     * @var array<int|string, string|DomQueryInterface>
     */
    protected array $mapping = [];

    protected null|string|DomQueryInterface $singleSelector = null;

    /**
     * @param string|DomQueryInterface|array<int|string, string|DomQueryInterface> $selectorOrMapping
     */
    final public function __construct(
        string|DomQueryInterface|array $selectorOrMapping = []
    ) {
        $this->extract($selectorOrMapping);
    }

    public static function root(): static
    {
        $instance = new static();

        $instance->root = true;

        return $instance;
    }

    public static function each(string|DomQueryInterface $domQuery): static
    {
        $instance = new static();

        $instance->each = is_string($domQuery) ? $instance->makeDefaultDomQueryInstance($domQuery) : $domQuery;

        return $instance;
    }

    public static function first(string|DomQueryInterface $domQuery): static
    {
        $instance = new static();

        $instance->first = is_string($domQuery) ? $instance->makeDefaultDomQueryInstance($domQuery) : $domQuery;

        return $instance;
    }

    public static function last(string|DomQueryInterface $domQuery): static
    {
        $instance = new static();

        $instance->last = is_string($domQuery) ? $instance->makeDefaultDomQueryInstance($domQuery) : $domQuery;

        return $instance;
    }

    public static function cssSelector(string $selector): CssSelector
    {
        return new CssSelector($selector);
    }

    public static function xPath(string $query): XPathQuery
    {
        return new XPathQuery($query);
    }

    abstract protected function makeDefaultDomQueryInstance(string $query): DomQueryInterface;

    /**
     * @param string|DomQueryInterface|array<string|DomQueryInterface> $selectorOrMapping
     */
    public function extract(string|DomQueryInterface|array $selectorOrMapping): static
    {
        if (is_array($selectorOrMapping)) {
            $this->mapping = $selectorOrMapping;
        } else {
            $this->singleSelector = $selectorOrMapping;
        }

        return $this;
    }

    /**
     * @throws InvalidArgumentException
     */
    protected function validateAndSanitizeInput(mixed $input): Crawler
    {
        return new Crawler($this->validateAndSanitizeStringOrHttpResponse($input));
    }

    /**
     * @param Crawler $input
     * @throws Exception
     */
    protected function invoke(mixed $input): Generator
    {
        $base = $this->getBase($input);

        if ($base->count() === 0) {
            return;
        }

        if (empty($this->mapping) && $this->singleSelector) {
            yield from $this->singleSelector($base);
        } else {
            if ($this->each) {
                foreach ($base as $element) {
                    yield $this->mapProperties(new Crawler($element));
                }
            } else {
                yield $this->mapProperties($base);
            }
        }
    }

    protected function singleSelector(Crawler $domCrawler): Generator
    {
        if ($this->singleSelector === null) {
            return;
        }

        $domQuery = is_string($this->singleSelector) ?
            $this->makeDefaultDomQueryInstance($this->singleSelector) :
            $this->singleSelector;

        $outputs = $domQuery->apply($domCrawler);

        if (is_array($outputs)) {
            foreach ($outputs as $output) {
                yield $output;
            }
        } elseif ($outputs !== null) {
            yield $outputs;
        }
    }

    /**
     * @return mixed[]
     */
    protected function mapProperties(Crawler $domCrawler): array
    {
        $mappedProperties = [];

        foreach ($this->mapping as $key => $domQuery) {
            if (is_string($domQuery)) {
                $domQuery = $this->makeDefaultDomQueryInstance($domQuery);
            }

            $mappedProperties[$key] = $domQuery->apply($domCrawler);
        }

        return $mappedProperties;
    }

    /**
     * @throws Exception
     */
    protected function getBase(Crawler $domCrawler): Crawler
    {
        if ($this->root) {
            return $domCrawler;
        } elseif ($this->each) {
            return $this->each->filter($domCrawler);
        } elseif ($this->first) {
            return $this->first->filter($domCrawler)->first();
        } elseif ($this->last) {
            return $this->last->filter($domCrawler)->last();
        }

        throw new Exception('Invalid state: no base selector');
    }
}
