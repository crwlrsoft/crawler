<?php

namespace Crwlr\Crawler\Steps\Html;

use Crwlr\Crawler\Steps\Dom\HtmlDocument;
use Crwlr\Crawler\Steps\Dom\HtmlElement;
use Crwlr\Crawler\Steps\Dom\Node;
use Crwlr\Crawler\Steps\Dom\NodeList;
use Crwlr\Crawler\Steps\Dom\XmlElement;
use Crwlr\Html2Text\Exceptions\InvalidHtmlException;
use Crwlr\Html2Text\Html2Text;
use Crwlr\Url\Url;
use Exception;
use InvalidArgumentException;

abstract class DomQuery
{
    public ?string $attributeName = null;

    protected SelectorTarget $target = SelectorTarget::Text;

    protected bool $onlyFirstMatch = false;

    protected bool $onlyLastMatch = false;

    protected false|int $onlyNthMatch = false;

    protected bool $onlyEvenMatches = false;

    protected bool $onlyOddMatches = false;

    protected bool $toAbsoluteUrl = false;

    protected bool $withFragment = true;

    protected ?string $baseUrl = null;

    protected ?Html2Text $html2TextConverter = null;

    public function __construct(
        public readonly string $query,
    ) {}

    /**
     * @return string[]|string|null
     * @throws InvalidHtmlException|Exception
     */
    public function apply(Node $node): array|string|null
    {
        if ($this->toAbsoluteUrl && $node instanceof HtmlDocument) {
            $baseHref = $node->getBaseHref();

            if ($baseHref) {
                $this->setBaseUrl($baseHref);
            }
        }

        $filtered = $this->filter($node);

        if ($this->filtersMatches()) {
            $filtered = $this->filterMatches($filtered);

            if ($filtered === null) {
                return null;
            }
        }

        if ($filtered->count() > 1) {
            return $filtered->each(function ($element) {
                return $this->getTarget($element);
            });
        } elseif ($filtered->count() === 1) {
            $node = $filtered->first();

            if ($node instanceof HtmlElement || $node instanceof XmlElement) {
                return $this->getTarget($node);
            }
        }

        return null;
    }

    public function first(): self
    {
        $this->onlyFirstMatch = true;

        return $this;
    }

    public function last(): self
    {
        $this->onlyLastMatch = true;

        return $this;
    }

    public function nth(int $n): self
    {
        if ($n < 1) {
            throw new InvalidArgumentException('Argument $n must be greater than 0');
        }

        $this->onlyNthMatch = $n;

        return $this;
    }

    public function even(): self
    {
        $this->onlyEvenMatches = true;

        return $this;
    }

    public function odd(): self
    {
        $this->onlyOddMatches = true;

        return $this;
    }

    public function text(): self
    {
        $this->target = SelectorTarget::Text;

        return $this;
    }

    public function formattedText(?Html2Text $converter = null): self
    {
        $this->target = SelectorTarget::FormattedText;

        if ($converter) {
            $this->html2TextConverter = $converter;
        }

        return $this;
    }

    public function html(): self
    {
        $this->target = SelectorTarget::Html;

        return $this;
    }

    public function attribute(string $attributeName): self
    {
        $this->target = SelectorTarget::Attribute;

        $this->attributeName = $attributeName;

        return $this;
    }

    public function outerHtml(): self
    {
        $this->target = SelectorTarget::OuterHtml;

        return $this;
    }

    public function link(): self
    {
        $this->target = SelectorTarget::Attribute;

        $this->attributeName = 'href';

        $this->toAbsoluteUrl = true;

        return $this;
    }

    public function withoutFragment(): self
    {
        $this->withFragment = false;

        return $this;
    }

    /**
     * Call this method and the selected value will be converted to an absolute url when apply() is called.
     *
     * @return $this
     */
    public function toAbsoluteUrl(): self
    {
        $this->toAbsoluteUrl = true;

        return $this;
    }

    /**
     * Automatically called when used in a Dom step.
     *
     * @throws Exception
     */
    public function setBaseUrl(string $baseUrl): static
    {
        if (!empty($this->baseUrl)) {
            $this->baseUrl = Url::parse($this->baseUrl)->resolve($baseUrl)->__toString();
        } else {
            $this->baseUrl = $baseUrl;
        }

        return $this;
    }

    abstract protected function filter(Node $node): NodeList;

    protected function filtersMatches(): bool
    {
        return $this->onlyFirstMatch ||
            $this->onlyLastMatch ||
            $this->onlyNthMatch !== false ||
            $this->onlyEvenMatches ||
            $this->onlyOddMatches;
    }

    /**
     * @return NodeList|null
     * @throws Exception
     */
    protected function filterMatches(NodeList $matches): ?NodeList
    {
        if (
            $matches->count() === 0 ||
            ($this->onlyNthMatch !== false && $matches->count() < $this->onlyNthMatch)
        ) {
            return null;
        }

        if ($this->onlyFirstMatch) {
            $node = $matches->first();

            return $node ? new NodeList([$node]) : new NodeList([]);
        } elseif ($this->onlyLastMatch) {
            $node = $matches->last();

            return $node ? new NodeList([$node]) : new NodeList([]);
        } elseif ($this->onlyNthMatch !== false) {
            $node = $matches->nth($this->onlyNthMatch);

            return $node ? new NodeList([$node]) : new NodeList([]);
        } elseif ($this->onlyEvenMatches || $this->onlyOddMatches) {
            return $this->filterEvenOrOdd($matches);
        }

        return null;
    }

    /**
     * @param NodeList $domCrawler
     * @return NodeList
     */
    protected function filterEvenOrOdd(NodeList $domCrawler): NodeList
    {
        $nodes = [];

        $i = 1;

        foreach ($domCrawler as $node) {
            if (
                ($this->onlyEvenMatches && $i % 2 === 0) ||
                ($this->onlyOddMatches && $i % 2 !== 0)
            ) {
                $nodes[] = $node;
            }

            $i++;
        }

        return new NodeList($nodes);
    }

    /**
     * @throws InvalidHtmlException
     * @throws Exception
     */
    protected function getTarget(HtmlElement|XmlElement $node): string
    {
        if ($this->target === SelectorTarget::FormattedText) {
            if (!$this->html2TextConverter) {
                $this->html2TextConverter = new Html2Text();
            }

            $target = $this->html2TextConverter->convertHtmlToText(
                $node instanceof HtmlElement ? $node->outerHtml() : $node->outerXml(),
            );
        } elseif ($this->target === SelectorTarget::Html) {
            $target = $node instanceof HtmlElement ? trim($node->innerHtml()) : trim($node->innerXml());
        } elseif ($this->target === SelectorTarget::OuterHtml) {
            $target = $node instanceof HtmlElement ? trim($node->outerHtml()) : trim($node->outerXml());
        } else {
            $target = trim(
                $this->attributeName ?
                    ($node->getAttribute($this->attributeName) ?? '') :
                    (
                        method_exists($node, strtolower($this->target->name)) ?
                            $node->{strtolower($this->target->name)}() :
                            ''
                    ),
            );
        }

        if ($this->toAbsoluteUrl && $this->baseUrl !== null) {
            $target = $this->handleUrlFragment(Url::parse($this->baseUrl)->resolve($target));
        }

        if (str_contains($target, '�')) {
            $target = str_replace('�', '', $target);
        }

        return $target;
    }

    /**
     * @throws Exception
     */
    protected function handleUrlFragment(Url $url): Url
    {
        if (!$this->withFragment) {
            $url->fragment('');
        }

        return $url;
    }
}
