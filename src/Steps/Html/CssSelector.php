<?php

namespace Crwlr\Crawler\Steps\Html;

use Crwlr\Crawler\Steps\Html\Exceptions\InvalidDomQueryException;
use Symfony\Component\CssSelector\CssSelectorConverter;
use Symfony\Component\CssSelector\Exception\ExpressionErrorException;
use Symfony\Component\CssSelector\Exception\SyntaxErrorException;
use Symfony\Component\DomCrawler\Crawler;

final class CssSelector extends DomQuery
{
    /**
     * @throws InvalidDomQueryException
     */
    public function __construct(string $query)
    {
        try {
            (new CssSelectorConverter())->toXPath($query);
        } catch (ExpressionErrorException|SyntaxErrorException $exception) {
            throw InvalidDomQueryException::fromSymfonyException($query, $exception);
        }

        parent::__construct($query);
    }

    public function filter(Crawler $domCrawler): Crawler
    {
        return $domCrawler->filter($this->query);
    }
}
