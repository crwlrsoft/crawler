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
        return new class ($productToken) extends HttpCrawler {
            public function __construct(private readonly string $_botUserAgentProductToken)
            {
                parent::__construct();
            }

            protected function userAgent(): UserAgentInterface
            {
                return new BotUserAgent($this->_botUserAgentProductToken);
            }
        };
    }

    public function withUserAgent(string $userAgent): HttpCrawler
    {
        return new class ($userAgent) extends HttpCrawler {
            public function __construct(private readonly string $_userAgentString)
            {
                parent::__construct();
            }

            protected function userAgent(): UserAgentInterface
            {
                return new UserAgent($this->_userAgentString);
            }
        };
    }
}
