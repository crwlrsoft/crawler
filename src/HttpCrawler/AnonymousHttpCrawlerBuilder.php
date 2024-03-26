<?php

namespace Crwlr\Crawler\HttpCrawler;

use Crwlr\Crawler\HttpCrawler;
use Crwlr\Crawler\UserAgents\BotUserAgent;
use Crwlr\Crawler\UserAgents\UserAgent;
use Crwlr\Crawler\UserAgents\UserAgentInterface;

class AnonymousHttpCrawlerBuilder
{
    public function __construct() {}

    public function withBotUserAgent(string $productToken): HttpCrawler
    {
        $instance = new class () extends HttpCrawler {
            protected function userAgent(): UserAgentInterface
            {
                return new UserAgent('temp');
            }
        };

        $instance->setUserAgent(new BotUserAgent($productToken));

        return $instance;
    }

    public function withUserAgent(string $userAgent): HttpCrawler
    {
        $instance = new class () extends HttpCrawler {
            protected function userAgent(): UserAgentInterface
            {
                return new UserAgent('temp');
            }
        };

        $instance->setUserAgent(new UserAgent($userAgent));

        return $instance;
    }
}
