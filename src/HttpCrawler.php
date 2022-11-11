<?php

namespace Crwlr\Crawler;

use Crwlr\Crawler\Loader\Http\HttpLoader;
use Crwlr\Crawler\Loader\LoaderInterface;
use Crwlr\Crawler\UserAgents\UserAgentInterface;
use Psr\Log\LoggerInterface;

abstract class HttpCrawler extends Crawler
{
    protected function loader(UserAgentInterface $userAgent, LoggerInterface $logger): LoaderInterface
    {
        return new HttpLoader($userAgent, logger: $logger);
    }
}
