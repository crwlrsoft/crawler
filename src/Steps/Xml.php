<?php

namespace Crwlr\Crawler\Steps;

use Crwlr\Crawler\Steps\Html\DomQueryInterface;
use Crwlr\Crawler\Steps\Html\Exceptions\InvalidDomQueryException;
use Crwlr\Crawler\Steps\Html\XPathQuery;

class Xml extends Dom
{
    /**
     * @throws InvalidDomQueryException
     */
    public function makeDefaultDomQueryInstance(string $query): DomQueryInterface
    {
        return new XPathQuery($query);
    }
}
