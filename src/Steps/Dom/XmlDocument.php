<?php

namespace Crwlr\Crawler\Steps\Dom;

use Crwlr\Utils\PhpVersion;
use DOMNode;
use Symfony\Component\DomCrawler\Crawler;
use Throwable;
use voku\helper\ASCII;

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
            try {
                return \Dom\XMLDocument::createFromString($source, LIBXML_NOERROR | LIBXML_NONET);
            } catch (Throwable) {
                $source = $this->replaceInvalidXmlCharacters($source);

                try {
                    return \Dom\XMLDocument::createFromString($source, LIBXML_NOERROR | LIBXML_NONET);
                } catch (Throwable) {
                } // If it fails again, try it with symfony DOM Crawler as fallback.
            }
        }

        $crawler = new Crawler($source);

        if ($crawler->count() === 0) {
            $source = $this->replaceInvalidXmlCharacters($source);

            $crawler = new Crawler($source);
        }

        return $crawler;
    }

    /**
     * Replace characters that aren't valid within XML documents
     *
     * Sometimes XML parsing errors occur because of characters that aren't valid within XML documents.
     * Therefore, this method finds and replaces them with valid alternatives or HTML entities.
     * For best results in those cases, please install the voku/portable-ascii composer package.
     *
     * @param string $value
     * @return string
     */
    private function replaceInvalidXmlCharacters(string $value): string
    {
        return preg_replace_callback('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}]/u', function ($match) {
            $replacement = class_exists('voku\helper\ASCII') ? ASCII::to_transliterate($match[0]) : '?';

            if ($replacement === '?') {
                return '&#' . mb_ord($match[0]) . ';';
            }

            return $replacement;
        }, $value) ?? $value;
    }
}
