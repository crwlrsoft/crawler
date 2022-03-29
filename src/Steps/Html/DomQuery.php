<?php

namespace Crwlr\Crawler\Steps\Html;

use Symfony\Component\DomCrawler\Crawler;

abstract class DomQuery implements DomQueryInterface
{
    public ?string $attributeName = null;

    public function __construct(
        public readonly string $query,
        public SelectorTarget $target = SelectorTarget::Text
    ) {
    }

    /**
     * @return string|string[]
     */
    public function apply(Crawler $domCrawler): array|string
    {
        $filtered = $this->filter($domCrawler);

        if ($filtered->count() === 0) {
            return '';
        } elseif ($filtered->count() === 1) {
            return $this->getTarget($filtered);
        }

        return $filtered->each(function ($element) {
            return $this->getTarget($element);
        });
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

    private function getTarget(Crawler $filtered): string
    {
        return trim(
            $this->attributeName ?
                $filtered->{$this->target->name}($this->attributeName) :
                $filtered->{$this->target->name}()
        );
    }
}
