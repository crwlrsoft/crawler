<?php

namespace tests\Loader\Http\Politeness;

use Crwlr\Crawler\Loader\Http\HttpLoader;
use Crwlr\Crawler\Loader\Http\Politeness\RobotsTxtHandler;
use Crwlr\Crawler\Logger\CliLogger;
use Crwlr\Crawler\UserAgents\BotUserAgent;
use Crwlr\Crawler\UserAgents\UserAgent;
use Crwlr\Crawler\UserAgents\UserAgentInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Utils;
use Mockery;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;

function helper_getLoaderWithRobotsTxt(string $robotsTxtContent = '', ?UserAgentInterface $userAgent = null): HttpLoader
{
    if (!$userAgent) {
        $userAgent = new BotUserAgent('FooBot');
    }

    $httpClient = Mockery::mock(Client::class);

    if ($userAgent instanceof BotUserAgent) {
        $httpClient->shouldReceive('sendRequest')->withArgs(function (RequestInterface $request) {
            return str_ends_with($request->getUri()->__toString(), '/robots.txt');
        })->andReturn(new Response(200, [], Utils::streamFor($robotsTxtContent)));
    }

    return new HttpLoader($userAgent, $httpClient);
}

/** @var TestCase $this */

test('route is disallowed when it\'s disallowed for my user agent', function () {
    $robotsTxt = <<<ROBOTSTXT
        User-agent: FooBot
        Disallow: /foo/
        ROBOTSTXT;

    $loader = helper_getLoaderWithRobotsTxt($robotsTxt);

    $robotsTxt = new RobotsTxtHandler($loader);

    expect($robotsTxt->isAllowed('https://www.example.com/foo/bar'))->toBeFalse();
});

test('route is disallowed when it\'s disallowed for all user agents', function () {
    $robotsTxt = <<<ROBOTSTXT
        User-agent: *
        Disallow: /foo/
        ROBOTSTXT;

    $loader = helper_getLoaderWithRobotsTxt($robotsTxt);

    $robotsTxt = new RobotsTxtHandler($loader);

    expect($robotsTxt->isAllowed('https://www.example.com/foo/bar'))->toBeFalse();
});

test(
    'route is not disallowed when it\'s disallowed for all user agents but my user agent is not a BotUserAgent',
    function () {
        $robotsTxt = <<<ROBOTSTXT
            User-agent: *
            Disallow: /foo/
            ROBOTSTXT;

        $loader = helper_getLoaderWithRobotsTxt($robotsTxt, new UserAgent('Any User Agent'));

        $robotsTxt = new RobotsTxtHandler($loader);

        expect($robotsTxt->isAllowed('https://www.example.com/foo/bar'))->toBeTrue();
    },
);

test(
    'route is not disallowed when it\'s disallowed for all user agent but I want to ignore wildcard rules',
    function () {
        $robotsTxt = <<<ROBOTSTXT
            User-agent: *
            Disallow: /foo/
            ROBOTSTXT;

        $loader = helper_getLoaderWithRobotsTxt($robotsTxt);

        $robotsTxt = new RobotsTxtHandler($loader);

        $robotsTxt->ignoreWildcardRules();

        expect($robotsTxt->isAllowed('https://www.example.com/foo/bar'))->toBeTrue();
    },
);

it('gets all the sitemap URLs from robots.txt', function () {
    $robotsTxt = <<<ROBOTSTXT
        User-agent: *
        Disallow:

        Sitemap: https://www.example.com/sitemap.xml
        Sitemap: https://www.example.com/sitemap2.xml
        sitemap: https://www.example.com/sitemap3.xml
        ROBOTSTXT;

    $loader = helper_getLoaderWithRobotsTxt($robotsTxt);

    $robotsTxt = new RobotsTxtHandler($loader);

    expect($robotsTxt->getSitemaps('https://www.example.com/home'))->toBe([
        'https://www.example.com/sitemap.xml',
        'https://www.example.com/sitemap2.xml',
        'https://www.example.com/sitemap3.xml',
    ]);
});

it('fails silently when parsing fails', function () {
    $robotsTxt = <<<ROBOTSTXT
        Disallow: /
        ROBOTSTXT;

    $loader = helper_getLoaderWithRobotsTxt($robotsTxt);

    $robotsTxt = new RobotsTxtHandler($loader, new CliLogger());

    expect($robotsTxt->isAllowed('https://www.example.com/anything'))->toBeTrue();

    $logOutput = $this->getActualOutputForAssertion();

    expect($logOutput)->toContain('Failed to parse robots.txt');
});
