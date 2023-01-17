<?php

namespace Crwlr\Crawler;

use Crwlr\Crawler\Loader\Http\HttpLoader;
use Crwlr\Crawler\Loader\LoaderInterface;
use Crwlr\Crawler\UserAgents\UserAgentInterface;
use Psr\Log\LoggerInterface;

abstract class HttpCrawler extends Crawler
{
    /**
     * @return LoaderInterface|array<string, LoaderInterface>
     */
    protected function loader(UserAgentInterface $userAgent, LoggerInterface $logger): LoaderInterface|array
    {
        return new HttpLoader($userAgent, logger: $logger);
    }
}
