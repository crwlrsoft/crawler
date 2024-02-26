<?php

namespace tests\_Stubs\Crawlers;

use Crwlr\Crawler\Crawler;
use Crwlr\Crawler\Loader\LoaderInterface;
use Crwlr\Crawler\UserAgents\UserAgentInterface;
use Psr\Log\LoggerInterface;
use tests\_Stubs\Crawlers\DummyTwo\DummyTwoLoader;
use tests\_Stubs\Crawlers\DummyTwo\DummyTwoLogger;
use tests\_Stubs\Crawlers\DummyTwo\DummyTwoUserAgent;

/**
 * @property DummyTwoUserAgent $userAgent
 * @property DummyTwoLogger $logger
 * @property DummyTwoLoader $loader
 * @method DummyTwoUserAgent getUserAgent()
 * @method DummyTwoLogger getLogger()
 * @method DummyTwoLoader getLoader()
 */

class DummyTwo extends Crawler
{
    public int $userAgentCalled = 0;

    public int $loggerCalled = 0;

    public int $loaderCalled = 0;

    /**
     * @return DummyTwoUserAgent
     */
    protected function userAgent(): UserAgentInterface
    {
        $this->userAgentCalled += 1;

        return new DummyTwoUserAgent('FooBot');
    }

    /**
     * @return DummyTwoLogger
     */
    protected function logger(): LoggerInterface
    {
        $this->loggerCalled += 1;

        return new DummyTwoLogger();
    }

    /**
     * @return DummyTwoLoader
     */
    protected function loader(UserAgentInterface $userAgent, LoggerInterface $logger): LoaderInterface
    {
        $this->loaderCalled += 1;

        return new DummyTwoLoader($userAgent, null, $logger);
    }
}
