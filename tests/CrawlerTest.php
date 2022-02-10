<?php

namespace tests;

use Crwlr\Crawler\Crawler;
use Crwlr\Crawler\Loader\PoliteHttpLoader;
use Crwlr\Crawler\Logger\CliLogger;
use Crwlr\Crawler\Output;
use Crwlr\Crawler\Steps\GroupInterface;
use Crwlr\Crawler\Steps\Loading\LoadingStepInterface;
use Crwlr\Crawler\Steps\StepInterface;
use Crwlr\Crawler\UserAgent;
use Mockery;

function helper_getDummyCrawler(): Crawler
{
    $crawler = new Crawler();
    $userAgent = new UserAgent('FooBot');
    $crawler->setUserAgent($userAgent);
    $crawler->setLoader(new PoliteHttpLoader($userAgent));
    $crawler->setLogger(new CliLogger());

    return $crawler;
}

test('You can set a UserAgent', function () {
    $userAgent = new UserAgent('FooBot');
    $crawler = new Crawler();
    $crawler->setUserAgent($userAgent);

    expect($crawler->userAgent())->toBe($userAgent);
});

test('You can set a Loader', function () {
    $loader = new PoliteHttpLoader(new UserAgent('FooBot'));
    $crawler = new Crawler();
    $crawler->setLoader($loader);

    expect($crawler->loader())->toBe($loader);
});

test('You can set a Logger', function () {
    $logger = new CliLogger();
    $crawler = new Crawler();
    $crawler->setLogger($logger);

    expect($crawler->logger())->toBe($logger);
});

test('You can add steps and the Crawler class passes on its Logger and also its Loader if needed', function () {
    $step = Mockery::mock(StepInterface::class);
    $step->shouldReceive('addLogger')->once();
    $crawler = helper_getDummyCrawler();
    $crawler->setLoader(Mockery::mock(PoliteHttpLoader::class));
    $crawler->addStep($step);

    $step = Mockery::mock(LoadingStepInterface::class);
    $step->shouldReceive('addLogger')->once();
    $step->shouldReceive('addLoader')->once();
    $crawler->addStep($step);
});

test('You can add steps and they are invoked when the Crawler is run', function () {
    $step = Mockery::mock(StepInterface::class);
    $step->shouldReceive('invokeStep')->once()->andReturn([new Output('👍🏻')]);
    $step->shouldReceive('addLogger')->once();
    $crawler = helper_getDummyCrawler();
    $crawler->setLoader(Mockery::mock(PoliteHttpLoader::class));
    $crawler->addStep($step);

    $crawler->run('randomInput');
});

test('You can add step groups and the Crawler class passes on its Logger and Loader', function () {
    $group = Mockery::mock(GroupInterface::class);
    $group->shouldReceive('addLogger')->once();
    $group->shouldReceive('addLoader')->once();
    $crawler = helper_getDummyCrawler();
    $crawler->setLoader(Mockery::mock(PoliteHttpLoader::class));
    $crawler->addGroup($group);
});

test('You can add step groups and they are invoked when the Crawler is run', function () {
    $group = Mockery::mock(GroupInterface::class);
    $group->shouldReceive('invokeStep')->once()->andReturn([new Output('👍🏻')]);
    $group->shouldReceive('addLogger')->once();
    $group->shouldReceive('addLoader')->once();
    $crawler = helper_getDummyCrawler();
    $crawler->setLoader(Mockery::mock(PoliteHttpLoader::class));
    $crawler->addGroup($group);

    $crawler->run('randomInput');
});
