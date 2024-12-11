<?php

namespace Crwlr\Crawler\Steps\Dom;

use Crwlr\Utils\PhpVersion;
use DOMNode;
use Symfony\Component\DomCrawler\Crawler;

use const DOM\HTML_NO_DEFAULT_NS;

/**
 * @method HtmlElement|null querySelector(string $selector)
 * @method NodeList<int, HtmlElement> querySelectorAll(string $selector)
 * @method NodeList<int, HtmlElement> queryXPath(string $selector)
 */

class HtmlDocument extends DomDocument
{
    /**
     * Gets the href attribute of a <base> tag in the document
     *
     * In case there are multiple base elements in the document:
     * https://developer.mozilla.org/en-US/docs/Web/HTML/Element/base
     * "If multiple <base> elements are used, only the first href and first target are obeyed..."
     */
    public function getBaseHref(): ?string
    {
        $baseTag = $this->querySelector('base');

        return $baseTag?->getAttribute('href');
    }

    public function outerHtml(): string
    {
        return $this->outerSource();
    }

    /**
     * @param \Dom\Node|DOMNode|Crawler $node
     */
    protected function makeChildNodeInstance(object $node): Node
    {
        return new HtmlElement($node);
    }

    /**
     * @return \Dom\HTMLDocument|Crawler
     */
    protected function makeDocumentInstance(string $source): object
    {
        $source = $this->fixInvalidCharactersInSource($source);

        if (PhpVersion::isAtLeast(8, 4)) {
            return \Dom\HTMLDocument::createFromString($source, HTML_NO_DEFAULT_NS | LIBXML_NOERROR);
        }

        return new Crawler($source);
    }

    /**
     * Converts charset to HTML-entities to ensure valid parsing.
     */
    private function fixInvalidCharactersInSource(string $source): string
    {
        if (function_exists('iconv')) {
            $charset = preg_match('//u', $source) ? 'UTF-8' : 'ISO-8859-1';

            preg_match('/(charset *= *["\']?)([a-zA-Z\-0-9_:.]+)/i', $source, $matches);

            if ($matches && !empty($matches[2])) {
                $declaredCharset = strtoupper($matches[2]);
            } else {
                $declaredCharset = null;
            }

            if ($charset === 'ISO-8859-1' && $declaredCharset === 'UTF-8') {
                $fixedSource = iconv("ISO-8859-1", "UTF-8//TRANSLIT", $source);

                if ($fixedSource !== false) {
                    $source = $fixedSource;
                }
            }
        }

        return $source;
    }
}
