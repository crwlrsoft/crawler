<?php

namespace Crwlr\Crawler;

use Crwlr\Crawler\Loader\Http\PoliteHttpLoader;
use Crwlr\Crawler\Loader\LoaderInterface;
use Crwlr\Crawler\UserAgents\UserAgentInterface;
use GuzzleHttp\Client;
use Psr\Http\Client\ClientInterface;
use Psr\Log\LoggerInterface;

abstract class HttpCrawler extends Crawler
{
    public function loader(UserAgentInterface $userAgent, LoggerInterface $logger): LoaderInterface
    {
        return new PoliteHttpLoader($userAgent, $this->httpClient(), $logger);
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
