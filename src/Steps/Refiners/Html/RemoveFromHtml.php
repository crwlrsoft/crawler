<?php

namespace Crwlr\Crawler\Steps\Refiners\Html;

use Crwlr\Crawler\Steps\Dom;
use Crwlr\Crawler\Steps\Dom\HtmlDocument;
use Crwlr\Crawler\Steps\Html\CssSelector;
use Crwlr\Crawler\Steps\Html\DomQuery;
use Crwlr\Crawler\Steps\Html\Exceptions\InvalidDomQueryException;
use Crwlr\Crawler\Steps\Refiners\AbstractRefiner;
use Throwable;

class RemoveFromHtml extends AbstractRefiner
{
    public function __construct(protected string|DomQuery $selector) {}

    public function refine(mixed $value): mixed
    {
        $selectorString = is_string($this->selector) ? $this->selector : $this->selector->query;

        if (trim($selectorString) === '') {
            $this->logger?->warning('If you want HTML nodes to be removed, please define a selector for those nodes.');

            return $value;
        }

        if (is_string($this->selector)) {
            try {
                $this->selector = Dom::cssSelector($this->selector);
            } catch (InvalidDomQueryException $exception) {
                $this->logger?->error('Invalid selector in refiner to remove HTML: ' . $exception->getMessage());

                return $value;
            }
        }

        try {
            $document = new HtmlDocument($value);
        } catch (Throwable $exception) {
            $this->logger?->warning(
                'Failed parsing output as HTML in refiner to remove nodes from HTML: ' . $exception->getMessage(),
            );

            return $value;
        }

        if ($this->selector instanceof CssSelector) {
            $document->removeNodesMatchingSelector($this->selector->query);
        } else {
            $document->removeNodesMatchingXPath($this->selector->query);
        }

        if (str_contains($value, '<html') || str_contains($value, '<HTML')) {
            return $document->outerHtml();
        }

        return $document->querySelector('body')?->innerHtml() ?? $document->outerHtml();
    }
}
