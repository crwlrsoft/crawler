<?php

namespace Crwlr\Crawler;

use Crwlr\Crawler\Loader\Http\HttpLoader;
use Crwlr\Crawler\Loader\LoaderInterface;
use Crwlr\Crawler\UserAgents\UserAgentInterface;
use GuzzleHttp\Client;
use Psr\Http\Client\ClientInterface;
use Psr\Log\LoggerInterface;

abstract class HttpCrawler extends Crawler
{
    protected function loader(UserAgentInterface $userAgent, LoggerInterface $logger): LoaderInterface
    {
        return new HttpLoader($userAgent, $this->httpClient(), $logger);
    }

    /**
     * Returns the default http client.
     * If you want to use a customized http client instance, implement the same method in the child class,
     * returning your customized Client instance.
     *
     * @return ClientInterface
     */
    protected function httpClient(): ClientInterface
    {
        return new Client();
    }
}
