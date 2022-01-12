<?php

namespace Crwlr\Crawler\Steps;

use Crwlr\Crawler\Steps\Html\GetLinks;
use Crwlr\Crawler\Steps\Html\QuerySelector;
use Crwlr\Crawler\Steps\Html\QuerySelectorAll;

class Html
{
    public static function querySelector(string $selector): QuerySelector
    {
        return new QuerySelector($selector);
    }

    public static function querySelectorAll(string $selector): QuerySelectorAll
    {
        return new QuerySelectorAll($selector);
    }

    public static function getLinks(?string $selector = null): GetLinks
    {
        return new GetLinks($selector);
    }
}
