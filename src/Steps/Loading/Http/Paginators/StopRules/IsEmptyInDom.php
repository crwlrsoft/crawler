<?php

namespace Crwlr\Crawler\Steps\Loading\Http\Paginators\StopRules;

use Crwlr\Crawler\Cache\Exceptions\MissingZlibExtensionException;
use Crwlr\Crawler\Loader\Http\Messages\RespondedRequest;
use Crwlr\Crawler\Steps\Dom\DomDocument;
use Crwlr\Crawler\Steps\Dom\HtmlElement;
use Crwlr\Crawler\Steps\Dom\XmlElement;
use Crwlr\Crawler\Steps\Html\CssSelector;
use Crwlr\Crawler\Steps\Html\DomQuery;
use Crwlr\Crawler\Steps\Html\Exceptions\InvalidDomQueryException;
use Crwlr\Crawler\Steps\Loading\Http;
use Psr\Http\Message\RequestInterface;
use Throwable;

abstract class IsEmptyInDom implements StopRule
{
    public function __construct(protected string|DomQuery $selector) {}

    /**
     * @throws InvalidDomQueryException|MissingZlibExtensionException
     */
    public function shouldStop(RequestInterface $request, ?RespondedRequest $respondedRequest): bool
    {
        if (!$respondedRequest) {
            return true;
        }

        $source = trim(Http::getBodyString($respondedRequest->response));

        try {
            $document = $this->makeDom($source);
        } catch (Throwable $exception) {
            return true;
        }

        $domQuery = $this->selector instanceof DomQuery ? $this->selector : new CssSelector($this->selector);

        $filtered = $domQuery instanceof CssSelector ?
            $document->querySelectorAll($domQuery->query) :
            $document->queryXPath($domQuery->query);

        if ($filtered->count() === 0) {
            return true;
        }

        foreach ($filtered as $element) {
            /** @var HtmlElement|XmlElement $element */
            if (!$this->nodeIsEmpty($element)) {
                return false;
            }
        }

        return true;
    }

    abstract protected function makeDom(string $source): DomDocument;

    private function nodeIsEmpty(HtmlElement|XmlElement $node): bool
    {
        return $node instanceof HtmlElement ? trim($node->innerHtml()) === '' : trim($node->innerXml()) === '';
    }
}
