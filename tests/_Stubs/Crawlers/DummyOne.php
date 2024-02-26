<?php

namespace tests\_Stubs\Crawlers;

use Crwlr\Crawler\Crawler;
use Crwlr\Crawler\Loader\LoaderInterface;
use Crwlr\Crawler\UserAgents\BotUserAgent;
use Crwlr\Crawler\UserAgents\UserAgentInterface;
use Mockery;
use Psr\Log\LoggerInterface;

class DummyOne extends Crawler
{
    /**
     * @return BotUserAgent
     */
    public function userAgent(): UserAgentInterface
    {
        return new BotUserAgent('FooBot');
    }

    public function loader(UserAgentInterface $userAgent, LoggerInterface $logger): LoaderInterface
    {
        return Mockery::mock(LoaderInterface::class);
    }
}
