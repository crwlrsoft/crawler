<?php

namespace Crwlr\Crawler\Steps\Dom;

use DOMNode;
use Symfony\Component\DomCrawler\Crawler;

/**
 * @method XmlElement|null querySelector(string $selector)
 * @method NodeList<int, XmlElement> querySelectorAll(string $selector)
 * @method NodeList<int, XmlElement> queryXPath(string $selector)
 */

class XmlElement extends Node
{
    public function outerXml(): string
    {
        return $this->outerSource();
    }

    public function innerXml(): string
    {
        return $this->innerSource();
    }

    /**
     * @param \Dom\Node|DOMNode|Crawler $node
     */
    protected function makeChildNodeInstance(object $node): Node
    {
        return new XmlElement($node);
    }
}
