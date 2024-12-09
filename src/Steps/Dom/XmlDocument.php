<?php

namespace Crwlr\Crawler\Steps\Dom;

use Crwlr\Utils\PhpVersion;
use DOMNode;
use Symfony\Component\DomCrawler\Crawler;

/**
 * @method XmlElement|null querySelector(string $selector)
 * @method NodeList<int, XmlElement> querySelectorAll(string $selector)
 * @method NodeList<int, XmlElement> queryXPath(string $selector)
 */

class XmlDocument extends DomDocument
{
    public function outerXml(): string
    {
        return $this->outerSource();
    }

    /**
     * @param \Dom\Node|DOMNode|Crawler $node
     */
    protected function makeChildNodeInstance(object $node): Node
    {
        return new XmlElement($node);
    }

    /**
     * @return \Dom\XMLDocument|Crawler
     */
    protected function makeDocumentInstance(string $source): object
    {
        if (PhpVersion::isAtLeast(8, 4)) {
            return \Dom\XMLDocument::createFromString($source, LIBXML_NOERROR | LIBXML_NONET);
        }

        return new Crawler($source);
    }
}
