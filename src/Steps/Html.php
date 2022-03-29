<?php

namespace Crwlr\Crawler\Steps;

use Crwlr\Crawler\Steps\Html\CssSelector;
use Crwlr\Crawler\Steps\Html\DomQueryInterface;
use Crwlr\Crawler\Steps\Html\GetLink;
use Crwlr\Crawler\Steps\Html\GetLinks;

class Html extends Dom
{
    public static function getLink(string $selector): GetLink
    {
        return new GetLink($selector);
    }

    public static function getLinks(string $selector): GetLinks
    {
        return new GetLinks($selector);
    }

    protected function makeDefaultDomQueryInstance(string $query): DomQueryInterface
    {
        return new CssSelector($query);
    }
}
