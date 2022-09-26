<?php

namespace tests\Loader\Http;

use Crwlr\Crawler\Loader\Http\Exceptions\LoadingException;
use Crwlr\Crawler\Loader\Http\HttpLoader;
use Crwlr\Crawler\Loader\Http\Messages\RespondedRequest;
use Crwlr\Crawler\UserAgents\BotUserAgent;
use Crwlr\Crawler\UserAgents\UserAgent;
use GuzzleHttp\Psr7\Response;
use Mockery;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;

use function tests\helper_getDummyRobotsTxtResponse;

function helper_wait100ms(): void
{
    $start = microtime(true);
    while ((microtime(true) - $start) < 0.1) {
    }
}

it('throttles requests to the same domain', function ($loadingMethod) {
    $httpClient = Mockery::mock(ClientInterface::class);

    $httpClient->shouldReceive('sendRequest')->once()->andReturnUsing(function (RequestInterface $request) {
        $response = new Response(200, [], $request->getUri()->__toString() . ' response');

        helper_wait100ms();

        return $response;
    });

    $httpClient->shouldReceive('sendRequest')->once()->andReturn(new Response(200));

    $loader = new HttpLoader(new UserAgent('SomeUserAgent'), $httpClient);

    $loader->{$loadingMethod}('https://www.example.com/foo');

    $firstResponse = microtime(true);

    $loader->{$loadingMethod}('https://www.example.com/bar');

    $secondResponse = microtime(true);

    $diff = $secondResponse - $firstResponse;

    expect($diff)->toBeGreaterThan(0.05);

    expect($diff)->toBeLessThan(0.12);
})->with(['load', 'loadOrFail']);

it('does not throttle requests to different domains', function ($loadingMethod) {
    $httpClient = Mockery::mock(ClientInterface::class);

    $httpClient->shouldReceive('sendRequest')->once()->andReturnUsing(function (RequestInterface $request) {
        $response = new Response(200, [], $request->getUri()->__toString() . ' response');

        helper_wait100ms();

        return $response;
    });

    $httpClient->shouldReceive('sendRequest')->once()->andReturn(new Response(200));

    $loader = new HttpLoader(new UserAgent('SomeUserAgent'), $httpClient);

    $loader->{$loadingMethod}('https://www.example.com/foo');

    $firstResponse = microtime(true);

    $loader->{$loadingMethod}('https://www.example.org/bar');

    $secondResponse = microtime(true);

    $diff = $secondResponse - $firstResponse;

    expect($diff)->toBeLessThan(0.001);
})->with(['load', 'loadOrFail']);

it('respects rules from robots.txt from load method', function () {
    $client = Mockery::mock(ClientInterface::class);

    $client->shouldReceive('sendRequest')->once()->andReturn(helper_getDummyRobotsTxtResponse());

    $loader = new HttpLoader(new BotUserAgent('FooBot'), $client);

    $response = $loader->load('https://www.crwlr.software/secret');

    expect($response)->toBeNull();

    $output = $this->getActualOutput(); // @phpstan-ignore-line

    expect($output)->toContain('Loaded https://www.crwlr.software/robots.txt');

    expect($output)->toContain('Crawler is not allowed to load https://www.crwlr.software/secret');
});

it('respects rules from robots.txt from loadOrFail method', function () {
    $client = Mockery::mock(ClientInterface::class);

    $client->shouldReceive('sendRequest')->once()->andReturn(helper_getDummyRobotsTxtResponse());

    $loader = new HttpLoader(new BotUserAgent('FooBot'), $client);

    $loader->loadOrFail('https://www.crwlr.software/secret');
})->throws(LoadingException::class);

it('does not respect rules from robots.txt when user agent isn\'t instance of BotUserAgent', function () {
    $client = Mockery::mock(ClientInterface::class);

    $client->shouldReceive('sendRequest')->once()->andReturn(helper_getDummyRobotsTxtResponse());

    $loader = new HttpLoader(new UserAgent('FooBot'), $client);

    $response = $loader->load('https://www.crwlr.software/secret');

    expect($response)->toBeInstanceOf(RespondedRequest::class);

    $output = $this->getActualOutput(); // @phpstan-ignore-line

    expect($output)->not()->toContain('Loaded https://www.crwlr.software/robots.txt');

    expect($output)->not()->toContain('Crawler is not allowed to load https://www.crwlr.software/secret');
});
