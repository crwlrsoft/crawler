<?php

namespace Crwlr\Crawler\Steps\Loading\Http\Paginators\StopRules;

use Crwlr\Crawler\Steps\Dom\DomDocument;
use Crwlr\Crawler\Steps\Dom\HtmlDocument;

class IsEmptyInHtml extends IsEmptyInDom
{
    protected function makeDom(string $source): DomDocument
    {
        return new HtmlDocument($source);
    }
}
