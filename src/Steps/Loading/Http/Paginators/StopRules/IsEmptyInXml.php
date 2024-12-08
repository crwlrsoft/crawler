<?php

namespace Crwlr\Crawler\Steps\Loading\Http\Paginators\StopRules;

use Crwlr\Crawler\Steps\Dom\DomDocument;
use Crwlr\Crawler\Steps\Dom\XmlDocument;

class IsEmptyInXml extends IsEmptyInDom
{
    protected function makeDom(string $source): DomDocument
    {
        return new XmlDocument($source);
    }
}
