<?php

namespace Crwlr\Crawler\Steps\Dom;

use Dom\Document;
use DOMNode;
use Symfony\Component\DomCrawler\Crawler;

abstract class Node
{
    /**
     * @var \Dom\Node|Crawler
     */
    private object $node;

    /**
     * @param \Dom\Node|DOMNode|Crawler $node
     */
    public function __construct(object $node)
    {
        if ($node instanceof DOMNode) {
            $node = new Crawler($node);
        }

        $this->node = $node;
    }

    public function querySelector(string $selector): ?Node
    {
        if ($this->node instanceof Crawler) {
            $filtered = $this->node->filter($selector);

            return $filtered->count() > 0 ? $this->makeChildNodeInstance($filtered->first()) : null;
        }

        $result = $this->node->querySelector($selector);

        return $result !== null ? $this->makeChildNodeInstance($result) : null;
    }

    public function querySelectorAll(string $selector): NodeList
    {
        if ($this->node instanceof Crawler) {
            return $this->makeNodeListInstance($this->node->filter($selector));
        }

        return $this->makeNodeListInstance($this->node->querySelectorAll($selector));
    }

    /**
     * @deprecated As the usage of XPath queries is no longer an option with the new DOM API introduced in
     *             PHP 8.4, please switch to using CSS selectors instead!
     */
    public function queryXPath(string $query): NodeList
    {
        $node = $this->node;

        if (!$node instanceof Crawler) {
            $node = new Crawler($this->outerSource());
        }

        return $this->makeNodeListInstance($node->filterXPath($query));
    }

    public function nodeName(): string
    {
        if ($this->node instanceof Crawler) {
            $nodeName = $this->node->nodeName();
        } else {
            $nodeName = $this->node->nodeName ?? '';
        }

        return strtolower($nodeName);
    }

    public function text(): string
    {
        if ($this->node instanceof Crawler) {
            $text = $this->node->text();
        } else {
            $text = $this->node->textContent ?? '';
        }

        return trim(
            preg_replace("/(?:[ \n\r\t\x0C]{2,}+|[\n\r\t\x0C])/", ' ', $text),
            " \n\r\t\x0C",
        );
    }

    public function getAttribute(string $attributeName): ?string
    {
        if ($this->node instanceof Crawler) {
            return $this->node->attr($attributeName);
        }

        return $this->node->getAttribute($attributeName);
    }

    /**
     * @param \Dom\Node|DOMNode|Crawler $node
     */
    abstract protected function makeChildNodeInstance(object $node): Node;

    protected function outerSource(): string
    {
        if ($this->node instanceof Crawler) {
            return $this->node->outerHtml();
        }

        $parentDocument = $this->getParentDocumentOfNode($this->node);

        if ($parentDocument instanceof \Dom\HTMLDocument) {
            return $parentDocument->saveHTML($this->node);
        } elseif ($parentDocument instanceof \Dom\XMLDocument) {
            return $parentDocument->saveXML($this->node);
        }

        return $this->node->innerHTML;
    }

    protected function innerSource(): string
    {
        if ($this->node instanceof Crawler) {
            return $this->node->html();
        }

        return $this->node->innerHTML;
    }

    /**
     * @param \Dom\NodeList|Crawler $nodeList
     */
    protected function makeNodeListInstance(object $nodeList): NodeList
    {
        return new NodeList(
            $nodeList,
            function (object $node): Node {
                /** @var DOMNode|\Dom\Node $node */
                return $this->makeChildNodeInstance($node);
            },
        );
    }

    /**
     * @param \Dom\Node $node
     * @return Document|null
     */
    private function getParentDocumentOfNode(object $node): ?object
    {
        if ($node instanceof Document) {
            return $node;
        }

        $parentDocument = $node->parentNode;

        while ($parentDocument && !$parentDocument instanceof Document) {
            $parentDocument = $parentDocument->parentNode;
        }

        if ($parentDocument instanceof Document) {
            return $parentDocument;
        }

        return null;
    }
}
