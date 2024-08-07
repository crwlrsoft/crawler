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
        $instance = new class extends HttpCrawler {
            protected function userAgent(): UserAgentInterface
            {
                return new UserAgent('temp');
            }
        };

        $instance->setUserAgent(new BotUserAgent($productToken));

        return $instance;
    }

    public function withUserAgent(string|UserAgentInterface $userAgent): HttpCrawler
    {
        $instance = new class extends HttpCrawler {
            protected function userAgent(): UserAgentInterface
            {
                return new UserAgent('temp');
            }
        };

        $userAgent = $userAgent instanceof UserAgentInterface ? $userAgent : new UserAgent($userAgent);

        $instance->setUserAgent($userAgent);

        return $instance;
    }

    public function withMozilla5CompatibleUserAgent(): HttpCrawler
    {
        return $this->withUserAgent(UserAgent::mozilla5CompatibleBrowser());
    }
}
