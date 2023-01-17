<?php

namespace tests\_Stubs;

use Crwlr\Crawler\Crawler;
use Crwlr\Crawler\Loader\Http\HttpLoader;
use Crwlr\Crawler\Loader\LoaderInterface;
use Crwlr\Crawler\UserAgents\UserAgent;
use Crwlr\Crawler\UserAgents\UserAgentInterface;
use Psr\Log\LoggerInterface;

class MultiLoaderCrawler extends Crawler
{
    protected function userAgent(): UserAgentInterface
    {
        return new UserAgent('Fooseragent');
    }

    protected function loader(UserAgentInterface $userAgent, LoggerInterface $logger): LoaderInterface|array
    {
        return [
            'http' => new HttpLoader($userAgent, logger: $logger),
            'phantasy' => new PhantasyLoader($userAgent, $logger),
            'phantasy2' => new PhantasyLoader($userAgent, $logger),
        ];
    }
}
