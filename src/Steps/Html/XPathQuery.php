<?php

namespace Crwlr\Crawler\Steps\Html;

use Crwlr\Crawler\Steps\Dom\Node;
use Crwlr\Crawler\Steps\Dom\NodeList;
use Crwlr\Crawler\Steps\Html\Exceptions\InvalidDomQueryException;
use DOMDocument;
use DOMXPath;

/**
 * @deprecated As the usage of XPath queries is no longer an option with the new DOM API introduced in
 *              PHP 8.4, please switch to using CSS selectors instead!
 */

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

    protected function filter(Node $node): NodeList
    {
        return $node->queryXPath($this->query);
    }

    /**
     * @throws InvalidDomQueryException
     */
    private function validateQuery(string $query): void
    {
        // Temporarily set a new error handler, so checking an invalid XPath query does not generate a PHP warning.
        set_error_handler(function ($errno, $errstr) {
            if ($errno === E_WARNING && $errstr === 'DOMXPath::evaluate(): Invalid expression') {
                return true;
            }

            return false;
        });

        $evaluation = (new DOMXPath(new DOMDocument()))->evaluate($query);

        restore_error_handler();

        if ($evaluation === false) {
            throw InvalidDomQueryException::make('Invalid XPath query', $query);
        }
    }
}
