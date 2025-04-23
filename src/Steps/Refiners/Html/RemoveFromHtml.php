<?php

namespace Crwlr\Crawler\Steps\Refiners\Html;

use Crwlr\Crawler\Steps\Dom;
use Crwlr\Crawler\Steps\Dom\HtmlDocument;
use Crwlr\Crawler\Steps\Html\CssSelector;
use Crwlr\Crawler\Steps\Html\DomQuery;
use Crwlr\Crawler\Steps\Html\Exceptions\InvalidDomQueryException;
use Crwlr\Crawler\Steps\Refiners\String\AbstractStringRefiner;
use Throwable;

class RemoveFromHtml extends AbstractStringRefiner
{
    protected DomQuery $selector;

    /**
     * @throws InvalidDomQueryException
     */
    public function __construct(string|DomQuery $selector)
    {
        $selectorString = is_string($selector) ? $selector : $selector->query;

        if (trim($selectorString) === '') {
            $this->logger?->warning(
                'Empty selector in remove HTML refiner. If you want HTML nodes to be removed, please define a ' .
                'selector for those nodes.',
            );
        }

        if (is_string($selector)) {
            $selector = Dom::cssSelector($selector);
        }

        $this->selector = $selector;
    }

    public function refine(mixed $value): mixed
    {
        return $this->apply($value, function ($value) {
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
        }, 'HtmlRefiner::remove()');
    }
}
