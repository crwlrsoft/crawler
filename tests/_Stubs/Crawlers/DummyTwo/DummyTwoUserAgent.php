<?php

namespace tests\_Stubs\Crawlers\DummyTwo;

use Crwlr\Crawler\UserAgents\BotUserAgent;

class DummyTwoUserAgent extends BotUserAgent
{
    public string $testProperty = 'foo';
}
