<?php

namespace Crwlr\Crawler\Steps\Dom;

use DOMNode;
use Symfony\Component\DomCrawler\Crawler;

/**
 * @method HtmlElement|null querySelector(string $selector)
 * @method NodeList<int, HtmlElement> querySelectorAll(string $selector)
 * @method NodeList<int, HtmlElement> queryXPath(string $selector)
 */

class HtmlElement extends Node
{
    public function outerHtml(): string
    {
        return $this->outerSource();
    }

    public function innerHtml(): string
    {
        return $this->innerSource();
    }

    public function html(): string
    {
        return $this->innerHtml();
    }

    /**
     * @param \Dom\Node|DOMNode|Crawler $node
     */
    protected function makeChildNodeInstance(object $node): Node
    {
        return new HtmlElement($node);
    }
}
