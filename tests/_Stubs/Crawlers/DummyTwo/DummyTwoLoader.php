<?php

namespace tests\_Stubs\Crawlers\DummyTwo;

use Crwlr\Crawler\Loader\Http\HttpLoader;

class DummyTwoLoader extends HttpLoader
{
    public string $testProperty = 'foo';
}
