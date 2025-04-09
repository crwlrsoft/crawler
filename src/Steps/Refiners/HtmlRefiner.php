<?php

namespace Crwlr\Crawler\Steps\Refiners;

use Crwlr\Crawler\Steps\Html\DomQuery;
use Crwlr\Crawler\Steps\Refiners\Html\RemoveFromHtml;

class HtmlRefiner
{
    public static function remove(string|DomQuery $selector): RemoveFromHtml
    {
        return new RemoveFromHtml($selector);
    }
}
