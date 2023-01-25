<?php

namespace Crwlr\Crawler\Steps\Html;

use Crwlr\Url\Url;
use InvalidArgumentException;
use Symfony\Component\DomCrawler\Crawler;

abstract class DomQuery implements DomQueryInterface
{
    public ?string $attributeName = null;

    protected SelectorTarget $target = SelectorTarget::Text;

    protected bool $onlyFirstMatch = false;

    protected bool $onlyLastMatch = false;

    protected false|int $onlyNthMatch = false;

    protected bool $onlyEvenMatches = false;

    protected bool $onlyOddMatches = false;

    protected bool $toAbsoluteUrl = false;

    protected ?string $baseUrl = null;

    public function __construct(
        public readonly string $query
    ) {
    }

    /**
     * When there is a <base> tag with a href attribute in an HTML document all links in the document must be resolved
     * against that base url. This method finds the base href in a document if there is one.
     */
    public static function getBaseHrefFromDocument(Crawler $document): ?string
    {
        $baseTag = $document->filter('base');

        if ($baseTag->count() > 0) {
            // https://developer.mozilla.org/en-US/docs/Web/HTML/Element/base
            // "If multiple <base> elements are used, only the first href and first target are obeyed..."
            $href = $baseTag->first()->attr('href');

            if (!empty($href)) {
                return $href;
            }
        }

        return null;
    }

    /**
     * @return string[]|string|null
     */
    public function apply(Crawler $domCrawler): array|string|null
    {
        if ($this->toAbsoluteUrl) {
            $baseHref = self::getBaseHrefFromDocument($domCrawler);

            if ($baseHref) {
                $this->setBaseUrl($baseHref);
            }
        }

        $filtered = $this->filter($domCrawler);

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
            return $this->getTarget($filtered);
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

    public function html(): self
    {
        $this->target = SelectorTarget::Html;

        return $this;
    }

    public function innerText(): self
    {
        $this->target = SelectorTarget::InnerText;

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

    protected function filtersMatches(): bool
    {
        return $this->onlyFirstMatch ||
            $this->onlyLastMatch ||
            $this->onlyNthMatch !== false ||
            $this->onlyEvenMatches ||
            $this->onlyOddMatches;
    }

    protected function filterMatches(Crawler $domCrawler): ?Crawler
    {
        if (
            $domCrawler->count() === 0 ||
            ($this->onlyNthMatch !== false && $domCrawler->count() < $this->onlyNthMatch)
        ) {
            return null;
        }

        if ($this->onlyFirstMatch) {
            return $domCrawler->first();
        } elseif ($this->onlyLastMatch) {
            return $domCrawler->last();
        } elseif ($this->onlyNthMatch !== false) {
            return new Crawler($domCrawler->getNode($this->onlyNthMatch - 1));
        } elseif ($this->onlyEvenMatches || $this->onlyOddMatches) {
            return $this->filterEvenOrOdd($domCrawler);
        }

        return null;
    }

    protected function filterEvenOrOdd(Crawler $domCrawler): Crawler
    {
        $newDomCrawler = new Crawler();

        $i = 1;

        foreach ($domCrawler as $node) {
            if (
                ($this->onlyEvenMatches && $i % 2 === 0) ||
                ($this->onlyOddMatches && $i % 2 !== 0)
            ) {
                $newDomCrawler->addNode($node);
            }

            $i++;
        }

        return $newDomCrawler;
    }

    protected function getTarget(Crawler $filtered): string
    {
        $target = trim(
            $this->attributeName ?
                $filtered->attr($this->attributeName) :
                $filtered->{strtolower($this->target->name)}()
        );

        if ($this->toAbsoluteUrl && $this->baseUrl !== null) {
            $target = Url::parse($this->baseUrl)->resolve($target);
        }

        return $target;
    }
}
