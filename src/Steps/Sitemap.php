<?php

namespace Crwlr\Crawler\Steps;

use Crwlr\Crawler\Steps\Loading\GetSitemapsFromRobotsTxt;
use Crwlr\Crawler\Steps\Sitemap\GetUrlsFromSitemap;

class Sitemap
{
    public static function getSitemapsFromRobotsTxt(): GetSitemapsFromRobotsTxt
    {
        return new GetSitemapsFromRobotsTxt();
    }

    public static function getUrlsFromSitemap(): GetUrlsFromSitemap
    {
        return new GetUrlsFromSitemap();
    }
}
