<?php

namespace Crwlr\Crawler\Steps\Html;

use Symfony\Component\DomCrawler\Crawler;

class XPathQuery extends DomQuery
{
    public function filter(Crawler $domCrawler): Crawler
    {
        return $domCrawler->filterXPath($this->query);
    }
}
