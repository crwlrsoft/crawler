<?php

namespace tests\_Stubs;

use Crwlr\Crawler\Loader\Loader;

class PhantasyLoader extends Loader
{
    public function load(mixed $subject): mixed
    {
        return 'loaded ' . $subject;
    }

    public function loadOrFail(mixed $subject): mixed
    {
        return 'loaded ' . $subject;
    }
}
