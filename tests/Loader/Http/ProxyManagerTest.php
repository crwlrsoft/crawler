<?php

namespace tests\Loader\Http;

use Crwlr\Crawler\Loader\Http\ProxyManager;

it('knows if it manages only one or multiple proxy server', function () {
    $manager = new ProxyManager(['http://127.0.0.1:8001']);

    expect($manager->hasOnlySingleProxy())
        ->toBeTrue()
        ->and($manager->hasMultipleProxies())
        ->toBeFalse();

    $manager = new ProxyManager(['http://127.0.0.1:8001', 'http://127.0.0.1:8002']);

    expect($manager->hasOnlySingleProxy())
        ->toBeFalse()
        ->and($manager->hasMultipleProxies())
        ->toBeTrue();
});

it('returns the proxy when only one is defined', function () {
    $manager = new ProxyManager(['http://127.0.0.1:8003']);

    expect($manager->getProxy())
        ->toBe('http://127.0.0.1:8003')
        ->and($manager->getProxy())
        ->toBe('http://127.0.0.1:8003');
});

it('rotates the proxies when multiple are defined', function () {
    $manager = new ProxyManager(['http://127.0.0.1:8001', 'http://127.0.0.1:8002', 'http://127.0.0.1:8003']);

    expect($manager->getProxy())
        ->toBe('http://127.0.0.1:8001')
        ->and($manager->getProxy())
        ->toBe('http://127.0.0.1:8002')
        ->and($manager->getProxy())
        ->toBe('http://127.0.0.1:8003')
        ->and($manager->getProxy())
        ->toBe('http://127.0.0.1:8001');
});
