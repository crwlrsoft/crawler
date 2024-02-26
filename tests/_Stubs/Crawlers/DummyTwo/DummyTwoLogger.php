<?php

namespace tests\_Stubs\Crawlers\DummyTwo;

use Crwlr\Crawler\Logger\CliLogger;

class DummyTwoLogger extends CliLogger
{
    public string $testProperty = 'foo';
}
