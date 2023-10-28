<?php

namespace tests\Loader;

use Crwlr\Crawler\Loader\Loader;
use Crwlr\Crawler\UserAgents\BotUserAgent;
use Mockery;
use Psr\SimpleCache\CacheInterface;

test('You can set multiple hook callbacks for one type and they are executed when called', function (string $hookName) {
    $loader = new class (new BotUserAgent('FooBot'), $hookName) extends Loader {
        public function __construct(BotUserAgent $userAgent, private readonly string $hookName)
        {
            parent::__construct($userAgent);
        }

        public function load(mixed $subject): mixed
        {
            $this->callHook($this->hookName);
            return 'something';
        }

        public function loadOrFail(mixed $subject): mixed
        {
            return 'something';
        }
    };
    $callback1Called = false;
    $loader->{$hookName}(function () use (&$callback1Called) {
        $callback1Called = true;
    });
    $callback2Called = false;
    $loader->{$hookName}(function () use (&$callback2Called) {
        $callback2Called = true;
    });
    $callback3Called = false;
    $loader->{$hookName}(function () use (&$callback3Called) {
        $callback3Called = true;
    });

    $loader->load('something');

    expect($callback1Called)->toBeTrue();
    expect($callback2Called)->toBeTrue();
    expect($callback3Called)->toBeTrue();
})->with([
    'beforeLoad',
    'onCacheHit',
    'onSuccess',
    'onError',
    'afterLoad',
]);

test('You can set a cache and use it in the load function', function () {
    $loader = new class (new BotUserAgent('FooBot')) extends Loader {
        public function load(mixed $subject): string
        {
            $this->cache?->get('foo');

            return 'something';
        }
        public function loadOrFail(mixed $subject): mixed
        {
            return 'something';
        }
    };

    $cache = Mockery::mock(CacheInterface::class);

    $cache->shouldReceive('get')->with('foo')->once();

    $loader->setCache($cache);

    $loader->load('something');
});
