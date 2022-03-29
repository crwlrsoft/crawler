<?php

namespace Crwlr\Crawler\Steps\Html;

use Symfony\Component\DomCrawler\Crawler;

final class CssSelector extends DomQuery
{
    public function filter(Crawler $domCrawler): Crawler
    {
        return $domCrawler->filter($this->query);
    }
}
