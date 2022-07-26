<?php

namespace Crwlr\Crawler\Steps\Html;

use Crwlr\Url\Url;
use Symfony\Component\DomCrawler\Crawler;

abstract class DomQuery implements DomQueryInterface
{
    public ?string $attributeName = null;

    private SelectorTarget $target = SelectorTarget::Text;

    private bool $toAbsoluteUrl = false;

    private ?string $baseUrl = null;

    public function __construct(
        public readonly string $query
    ) {
    }

    /**
     * @return string[]|string|null
     */
    public function apply(Crawler $domCrawler): array|string|null
    {
        $filtered = $this->filter($domCrawler);

        if ($filtered->count() > 1) {
            return $filtered->each(function ($element) {
                return $this->getTarget($element);
            });
        } elseif ($filtered->count() === 1) {
            return $this->getTarget($filtered);
        }

        return null;
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
        $this->baseUrl = $baseUrl;

        return $this;
    }

    private function getTarget(Crawler $filtered): string
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
