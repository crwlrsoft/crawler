<?php

namespace Crwlr\Crawler\Steps\Loading\Http\Paginators\StopRules;

use Crwlr\Crawler\Loader\Http\Messages\RespondedRequest;
use Crwlr\Crawler\Steps\Html\CssSelector;
use Crwlr\Crawler\Steps\Html\DomQueryInterface;
use Crwlr\Crawler\Steps\Loading\Http;
use Psr\Http\Message\RequestInterface;
use Symfony\Component\DomCrawler\Crawler;

abstract class IsEmptyInDom implements StopRule
{
    public function __construct(protected string|DomQueryInterface $selector) {}

    public function shouldStop(RequestInterface $request, ?RespondedRequest $respondedRequest): bool
    {
        if (!$respondedRequest) {
            return true;
        }

        $content = trim(Http::getBodyString($respondedRequest->response));

        $dom = new Crawler($content);

        $domQuery = $this->selector instanceof DomQueryInterface ? $this->selector : new CssSelector($this->selector);

        $filtered = $domQuery->filter($dom);

        if ($filtered->count() === 0) {
            return true;
        }

        foreach ($filtered as $element) {
            if (trim((new Crawler($element))->html()) !== '') {
                return false;
            }
        }

        return true;
    }
}
