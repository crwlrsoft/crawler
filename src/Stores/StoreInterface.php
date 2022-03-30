<?php

namespace Crwlr\Crawler\Stores;

use Crwlr\Crawler\Result;

interface StoreInterface
{
    public function store(Result $result): void;
}
