<?php

namespace Crwlr\Crawler\Steps\Html;

use Symfony\Component\DomCrawler\Crawler;

interface DomQueryInterface
{
    /**
     * @param Crawler $domCrawler
     * @return string[]|string
     */
    public function apply(Crawler $domCrawler): array|string;

    public function filter(Crawler $domCrawler): Crawler;
}
