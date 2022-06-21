<?php

namespace Crwlr\Crawler\Steps\Html;

use Symfony\Component\DomCrawler\Crawler;

interface DomQueryInterface
{
    /**
     * @param Crawler $domCrawler
     * @return string[]|string|null
     */
    public function apply(Crawler $domCrawler): array|string|null;

    public function filter(Crawler $domCrawler): Crawler;
}
