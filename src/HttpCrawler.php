<?php

namespace Crwlr\Crawler;

use Crwlr\Crawler\Loader\LoaderInterface;
use Crwlr\Crawler\Loader\PoliteHttpLoader;
use GuzzleHttp\Client;
use Psr\Http\Client\ClientInterface;

abstract class HttpCrawler extends Crawler
{
    public function loader(): LoaderInterface
    {
        if (!$this->loader) {
            $this->loader = new PoliteHttpLoader($this->userAgent(), $this->httpClient(), $this->logger());
        }

        return $this->loader;
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
