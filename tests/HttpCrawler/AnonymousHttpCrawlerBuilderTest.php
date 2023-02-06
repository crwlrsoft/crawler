<?php

use Crwlr\Crawler\HttpCrawler;
use Crwlr\Crawler\Loader\Http\HttpLoader;
use Crwlr\Crawler\UserAgents\BotUserAgent;
use Crwlr\Crawler\UserAgents\UserAgent;

it('builds an HttpCrawler instance with a bot user agent', function () {
    $crawler = HttpCrawler::make()->withBotUserAgent('YoloCrawler');

    expect($crawler)->toBeInstanceOf(HttpCrawler::class);

    expect($crawler->getLoader())->toBeInstanceOf(HttpLoader::class);

    $loader = $crawler->getLoader();

    /** @var HttpLoader $loader */

    expect($loader->userAgent())->toBeInstanceOf(BotUserAgent::class);

    $userAgent = $loader->userAgent();

    /** @var BotUserAgent $userAgent */

    expect($userAgent->productToken())->toBe('YoloCrawler');
});

it('creates an HttpCrawler instance with a non bot user agent', function () {
    $crawler = HttpCrawler::make()
        ->withUserAgent('Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 ...');

    expect($crawler)->toBeInstanceOf(HttpCrawler::class);

    expect($crawler->getLoader())->toBeInstanceOf(HttpLoader::class);

    $loader = $crawler->getLoader();

    /** @var HttpLoader $loader */

    expect($loader->userAgent())->toBeInstanceOf(UserAgent::class);

    $userAgent = $loader->userAgent();

    /** @var UserAgent $userAgent */

    expect($userAgent->__toString())->toBe('Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 ...');
});
