<?php

namespace Crwlr\Crawler\Steps\Html;

use Crwlr\Crawler\Steps\Dom\HtmlDocument;
use Crwlr\Crawler\Steps\Dom\Node;
use Crwlr\Crawler\Steps\Dom\NodeList;
use Crwlr\Crawler\Steps\Html\Exceptions\InvalidDomQueryException;
use Crwlr\Utils\PhpVersion;
use DOMException;
use Symfony\Component\CssSelector\CssSelectorConverter;
use Symfony\Component\CssSelector\Exception\ExpressionErrorException;
use Symfony\Component\CssSelector\Exception\SyntaxErrorException;

final class CssSelector extends DomQuery
{
    /**
     * @throws InvalidDomQueryException
     */
    public function __construct(string $query)
    {
        if (PhpVersion::isBelow(8, 4)) {
            try {
                (new CssSelectorConverter())->toXPath($query);
            } catch (ExpressionErrorException|SyntaxErrorException $exception) {
                throw InvalidDomQueryException::fromSymfonyException($query, $exception);
            }
        } else {
            try {
                (new HtmlDocument('<!doctype html><html></html>'))->querySelector($query);
            } catch (DOMException $exception) {
                throw InvalidDomQueryException::fromDomException($query, $exception);
            }
        }

        parent::__construct($query);
    }

    protected function filter(Node $node): NodeList
    {
        return $node->querySelectorAll($this->query);
    }
}
