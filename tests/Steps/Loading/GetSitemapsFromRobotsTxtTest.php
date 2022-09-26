<?php

namespace Crwlr\Crawler\Steps\Loading;

use Crwlr\Crawler\Input;
use Crwlr\Crawler\Loader\Http\HttpLoader;
use Crwlr\Crawler\Steps\Sitemap;
use Crwlr\Crawler\UserAgents\UserAgent;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Utils;
use Mockery;
use Psr\Http\Message\RequestInterface;

use function tests\helper_invokeStepWithInput;

it('gets all the sitemaps listed in the robots.txt file on a host, based on some URL on that host', function () {
    $httpClient = Mockery::mock(Client::class);

    $robotsTxt = <<<ROBOTSTXT
        User-agent: *
        Disallow:
        
        Sitemap: https://www.crwlr.software/sitemap.xml
        Sitemap: https://www.crwlr.software/sitemap2.xml
        
        Sitemap: https://www.crwlr.software/sitemap3.xml
        ROBOTSTXT;

    $httpClient->shouldReceive('sendRequest')
        ->once()
        ->withArgs(function (RequestInterface $request) {
            return $request->getUri()->__toString() === 'https://www.crwlr.software/robots.txt';
        })
        ->andReturn(new Response(200, body: Utils::streamFor($robotsTxt)));

    $loader = new HttpLoader(new UserAgent('SomeUserAgent'), $httpClient);

    $step = Sitemap::getSitemapsFromRobotsTxt()->addLoader($loader);

    $outputs = helper_invokeStepWithInput($step, new Input('https://www.crwlr.software/packages'));

    expect($outputs)->toHaveCount(3);
});
