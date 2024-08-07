<?php

namespace Crwlr\Crawler;

use Crwlr\Crawler\HttpCrawler\AnonymousHttpCrawlerBuilder;
use Crwlr\Crawler\Loader\Http\HttpLoader;
use Crwlr\Crawler\Loader\LoaderInterface;
use Crwlr\Crawler\UserAgents\UserAgentInterface;
use Psr\Log\LoggerInterface;

/**
 * @method HttpLoader getLoader()
 */

abstract class HttpCrawler extends Crawler
{
    /**
     * @return LoaderInterface
     */
    protected function loader(UserAgentInterface $userAgent, LoggerInterface $logger): LoaderInterface
    {
        return new HttpLoader($userAgent, logger: $logger);
    }

    public static function make(): HttpCrawler\AnonymousHttpCrawlerBuilder
    {
        return new AnonymousHttpCrawlerBuilder();
    }
}
