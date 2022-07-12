<?php

namespace tests\Loader\Http\Traits;

use Crwlr\Crawler\Loader\Http\HttpLoader;
use Crwlr\Crawler\Loader\Http\Traits\CheckRobotsTxt;
use Crwlr\Crawler\UserAgents\BotUserAgent;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use Mockery;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;

use function tests\helper_getDummyRobotsTxtResponse;

/** @var TestCase $this */

test(
    'It automatically loads an authority\'s robots.txt, parses it and checks if a certain uri is allowed',
    function () {
        $client = Mockery::mock(Client::class);
        $client->shouldReceive('sendRequest')->once()->withArgs(function (RequestInterface $request) {
            return $request->getUri()->__toString() === 'https://www.crwlr.software/robots.txt';
        })->andReturn(helper_getDummyRobotsTxtResponse());
        $client->shouldReceive('sendRequest')->once()->andReturn(new Response());
        $loader = new class (new BotUserAgent('FooBot'), $client) extends HttpLoader {
            use CheckRobotsTxt;
        };

        $loader->load('https://www.crwlr.software/packages');

        $output = $this->getActualOutput();
        expect($output)->toContain('Loaded https://www.crwlr.software/robots.txt');
        expect($output)->toContain('Loaded https://www.crwlr.software/packages');

        $loader->load('https://www.crwlr.software/secret');

        $output = $this->getActualOutput();
        expect($output)->toContain('Crawler ist not allowed to load https://www.crwlr.software/secret');
    }
);

test(
    'It "caches" robots.txt files for different authorities',
    function () {
        $client = Mockery::mock(Client::class);
        $client->shouldReceive('sendRequest')->once()->withArgs(function (RequestInterface $request) {
            return $request->getUri()->__toString() === 'https://www.crwlr.software/robots.txt';
        })->andReturn(helper_getDummyRobotsTxtResponse('crwlr.software'));
        $client->shouldReceive('sendRequest')->once()->withArgs(function (RequestInterface $request) {
            return $request->getUri()->__toString() === 'https://www.otsch.codes/robots.txt';
        })->andReturn(helper_getDummyRobotsTxtResponse('otsch.codes'));
        $client->shouldReceive('sendRequest')->times(3)->andReturn(new Response());
        $loader = new class (new BotUserAgent('FooBot'), $client) extends HttpLoader {
            use CheckRobotsTxt;
        };

        $loader->load('https://www.crwlr.software/packages');
        $loader->load('https://www.crwlr.software/crwlr.software/secret');
        $loader->load('https://www.otsch.codes/blog');
        $loader->load('https://www.crwlr.software/blog');
        $loader->load('https://www.otsch.codes/otsch.codes/secret');

        $output = $this->getActualOutput();
        // Check that it loaded both robots.txt files
        expect($output)->toContain('Loaded https://www.crwlr.software/robots.txt');
        expect($output)->toContain('Loaded https://www.otsch.codes/robots.txt');
        // Check that crwlr.software/robots.txt was loaded only once
        expect(explode('Loaded https://www.crwlr.software/robots.txt', $output))->toHaveCount(2);
        // Both secret requests have not been allowed
        expect($output)->toContain('Crawler ist not allowed to load https://www.crwlr.software/crwlr.software/secret');
        expect($output)->toContain('Crawler ist not allowed to load https://www.otsch.codes/otsch.codes/secret');
    }
);
