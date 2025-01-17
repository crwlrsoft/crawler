<?php

namespace tests\_Integration\Http;

use Crwlr\Crawler\HttpCrawler;
use Crwlr\Crawler\Loader\LoaderInterface;
use Crwlr\Crawler\Steps\Html;
use Crwlr\Crawler\Steps\Loading\Http;
use Crwlr\Crawler\UserAgents\BotUserAgent;
use Crwlr\Crawler\UserAgents\UserAgentInterface;
use Psr\Log\LoggerInterface;
use tests\_Stubs\DummyLogger;

use function tests\helper_generatorToArray;
use function tests\helper_getFastLoader;

/**
 * @method DummyLogger getLogger()
 */
class RobotsTxtCrawler extends HttpCrawler
{
    protected function logger(): LoggerInterface
    {
        return new DummyLogger();
    }

    protected function userAgent(): UserAgentInterface
    {
        return new BotUserAgent('MyBot');
    }

    public function loader(UserAgentInterface $userAgent, LoggerInterface $logger): LoaderInterface
    {
        return helper_getFastLoader($userAgent, $logger);
    }
}

it('does not warn about loader hooks being called multiple times', function () {
    // This occurred because the RobotsTxtHandler, used by the HttpLoader, loads the robots.txt via HttpLoader::load().
    // The call to the RobotsTxtHandler is triggered from within HttpLoader::load(), after the loader hooks
    // had already been reset at the start of the load() method. Resetting the loader hooks not only at the beginning
    // but also at the end of HttpLoader::load() resolves the issue.
    $crawler = new RobotsTxtCrawler();

    $crawler
        ->input('http://localhost:8000/hello-world')
        ->addStep(Http::get())
        ->addStep(Html::root()->extract('body')->keepAs('body'));

    $results = helper_generatorToArray($crawler->run());

    expect($results[0]->get('body'))->toBe('Hello World!');

    $logger = $crawler->getLogger();

    foreach ($logger->messages as $message) {
        expect($message['message'])->not->toContain(' was already called in this load call.');
    }
});

it('also does not warn about loader hooks being called multiple times when loadOrFail() is used', function () {
    // See comment in the test above.
    $crawler = new RobotsTxtCrawler();

    $crawler
        ->input('http://localhost:8000/hello-world')
        ->addStep(Http::get()->stopOnErrorResponse())
        ->addStep(Html::root()->extract('body')->keepAs('body'));

    $results = helper_generatorToArray($crawler->run());

    expect($results[0]->get('body'))->toBe('Hello World!');

    $logger = $crawler->getLogger();

    foreach ($logger->messages as $message) {
        expect($message['message'])->not->toContain(' was already called in this load call.');
    }
});
