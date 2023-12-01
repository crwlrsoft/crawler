<?php

namespace Crwlr\Crawler\Steps\Html;

use Crwlr\Crawler\Steps\Html\Exceptions\InvalidDomQueryException;
use DOMDocument;
use DOMXPath;
use Symfony\Component\DomCrawler\Crawler;

class XPathQuery extends DomQuery
{
    /**
     * @throws InvalidDomQueryException
     */
    public function __construct(string $query)
    {
        $this->validateQuery($query);

        parent::__construct($query);
    }

    public function filter(Crawler $domCrawler): Crawler
    {
        return $domCrawler->filterXPath($this->query);
    }

    /**
     * @throws InvalidDomQueryException
     */
    private function validateQuery(string $query): void
    {
        // Temporarily set a new error handler, so checking an invalid XPath query does not generate a PHP warning.
        $previousHandler = set_error_handler(function ($errno, $errstr) {
            if ($errno === E_WARNING && $errstr === 'DOMXPath::evaluate(): Invalid expression') {
                return true;
            }

            return false;
        });

        if ((new DOMXPath(new DOMDocument()))->evaluate($query) === false) {
            set_error_handler($previousHandler);

            throw InvalidDomQueryException::make('Invalid XPath query', $query);
        } else {
            set_error_handler($previousHandler);
        }
    }
}
