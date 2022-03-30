<?php

namespace tests\Loader\Http;

use Crwlr\Crawler\Loader\Http\Exceptions\LoadingException;
use Crwlr\Crawler\Loader\Http\PoliteHttpLoader;
use Crwlr\Crawler\UserAgents\BotUserAgent;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use Mockery;
use PHPUnit\Framework\TestCase;
use function tests\helper_getDummyRobotsTxtResponse;

function helper_wait100ms(): void
{
    $start = microtime(true);
    while ((microtime(true) - $start) < 0.1) {
    }
}

/** @var TestCase $this */

test('It waits politely in load method', function ($loadingMethod) {
    $client = Mockery::mock(Client::class);
    $client->shouldReceive('sendRequest')->once()->andReturnUsing(function ($request) {
        $response = new Response(200, [], $request->getUri()->__toString() . ' response');
        helper_wait100ms();
        return $response;
    });
    $client->shouldReceive('sendRequest')->once()->andReturn(new Response());
    $loader = new PoliteHttpLoader(new BotUserAgent('PoliteBot'), $client);

    $before = microtime(true);
    $loader->{$loadingMethod}('foo');
    $after = microtime(true);

    expect($after - $before)->toBeGreaterThan(0.125);
})->with(['load', 'loadOrFail']);

test('It respects robots.txt rules in load method', function () {
    $client = Mockery::mock(Client::class);
    $client->shouldReceive('sendRequest')->once()->andReturn(helper_getDummyRobotsTxtResponse());
    $loader = new PoliteHttpLoader(new BotUserAgent('FooBot'), $client);

    $loader->load('https://www.crwlr.software/secret');

    $output = $this->getActualOutput();
    expect($output)->toContain('Loaded https://www.crwlr.software/robots.txt');
    expect($output)->toContain('Crawler ist not allowed to load https://www.crwlr.software/secret');
});

test('It respects robots.txt rules in loadOrFail method', function () {
    $client = Mockery::mock(Client::class);
    $client->shouldReceive('sendRequest')->once()->andReturn(helper_getDummyRobotsTxtResponse());
    $loader = new PoliteHttpLoader(new BotUserAgent('FooBot'), $client);

    $loader->loadOrFail('https://www.crwlr.software/secret');
})->throws(LoadingException::class);
