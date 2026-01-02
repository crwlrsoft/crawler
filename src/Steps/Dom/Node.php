<?php

namespace Crwlr\Crawler\Steps\Dom;

use Dom\Document;
use Dom\XPath;
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

    public function queryXPath(string $query): NodeList
    {
        $node = $this->node;

        if (!$node instanceof Crawler) {
            $node = new Crawler($this->outerSource());
        }

        return $this->makeNodeListInstance($node->filterXPath($query));
    }

    public function removeNodesMatchingSelector(string $selector): void
    {
        foreach ($this->querySelectorAll($selector) as $node) {
            if ($node->node instanceof Crawler) {
                $node = $node->node->getNode(0);

                if ($node) {
                    $node->parentNode?->removeChild($node);
                }
            } else {
                $node->node->parentNode?->removeChild($node->node);
            }
        }
    }

    public function removeNodesMatchingXPath(string $query): void
    {
        if ($this->node instanceof Crawler) {
            foreach ($this->node->filterXPath($query) as $node) {
                $node->parentNode?->removeChild($node);
            }
        } else {
            $node = $this->getParentDocumentOfNode($this->node);

            if ($node) {
                $xpath = new XPath($node);

                foreach ($xpath->query($query) as $node) {
                    $node->parentNode?->removeChild($node);
                }
            }
        }
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
            $text = is_string($this->node->textContent) ? $this->node->textContent : '';
        }

        return trim(
            preg_replace("/(?:[ \n\r\t\x0C]{2,}+|[\n\r\t\x0C])/", ' ', $text) ?? $text,
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
            return $this->node->count() > 0 ? $this->node->outerHtml() : '';
        }

        if ($this->node instanceof Document) {
            $node = $this->node->documentElement;

            if ($this->node instanceof \Dom\HTMLDocument) {
                return $this->node->saveHTML($node);
            } elseif ($this->node instanceof \Dom\XMLDocument) {
                $source = $this->node->saveXML($node);

                return $source !== false ? $source : '';
            }
        }

        $parentDocument = $this->getParentDocumentOfNode($this->node);

        if ($parentDocument) {
            if ($parentDocument instanceof \Dom\HTMLDocument) {
                return $parentDocument->saveHTML($this->node);
            } elseif ($parentDocument instanceof \Dom\XMLDocument) {
                $source = $parentDocument->saveXML($this->node);

                return $source !== false ? $source : '';
            }
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
     * @param \Dom\NodeList<\Dom\Node>|Crawler $nodeList
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
