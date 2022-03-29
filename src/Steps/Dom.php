<?php

namespace Crwlr\Crawler\Steps;

use Crwlr\Crawler\Aggregates\RequestResponseAggregate;
use Crwlr\Crawler\Input;
use Crwlr\Crawler\Steps\Html\CssSelector;
use Crwlr\Crawler\Steps\Html\DomQueryInterface;
use Crwlr\Crawler\Steps\Html\XPathQuery;
use Exception;
use Generator;
use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\DomCrawler\Crawler;

abstract class Dom extends Step
{
    protected bool $root = false;
    protected ?DomQueryInterface $each = null;
    protected ?DomQueryInterface $first = null;
    protected ?DomQueryInterface $last = null;

    /**
     * @param array<string|CssSelector> $mapping
     */
    final public function __construct(protected array $mapping = [])
    {
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
     * @param mixed[] $mapping
     */
    public function extract(array $mapping): static
    {
        $this->mapping = $mapping;

        return $this;
    }

    protected function validateAndSanitizeInput(Input $input): mixed
    {
        $inputValue = $input->get();

        if (is_string($inputValue)) {
            return new Crawler($inputValue);
        }

        if ($inputValue instanceof ResponseInterface) {
            return new Crawler($inputValue->getBody()->getContents());
        }

        if ($inputValue instanceof RequestResponseAggregate) {
            return new Crawler($inputValue->response->getBody()->getContents());
        }

        throw new InvalidArgumentException('Input must be string, PSR-7 Response or RequestResponseAggregate.');
    }

    protected function invoke(Input $input): Generator
    {
        $domCrawler = $input->get();

        $base = $this->getBase($domCrawler);

        if ($this->each) {
            foreach ($base as $element) {
                yield $this->mapProperties(new Crawler($element));
            }
        } else {
            yield $this->mapProperties($base);
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
