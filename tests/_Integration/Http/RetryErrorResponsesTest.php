<?php

namespace tests\_Integration\Http;

use Crwlr\Crawler\HttpCrawler;
use Crwlr\Crawler\Loader\Http\HttpLoader;
use Crwlr\Crawler\Loader\Http\Politeness\RetryErrorResponseHandler;
use Crwlr\Crawler\Loader\LoaderInterface;
use Crwlr\Crawler\Steps\Loading\Http;
use Crwlr\Crawler\UserAgents\UserAgent;
use Crwlr\Crawler\UserAgents\UserAgentInterface;
use Psr\Log\LoggerInterface;

use function tests\helper_generatorToArray;

class RetryErrorResponsesCrawler extends HttpCrawler
{
    protected function userAgent(): UserAgentInterface
    {
        return new UserAgent('SomeBot');
    }

    public function loader(UserAgentInterface $userAgent, LoggerInterface $logger): LoaderInterface
    {
        return new HttpLoader(
            $userAgent,
            logger: $logger,
            retryErrorResponseHandler: new RetryErrorResponseHandler(2, [1, 2], 3),
        );
    }
}

it('retries after defined number of seconds', function ($path) {
    $crawler = new RetryErrorResponsesCrawler();

    $crawler->input('http://localhost:8000' . $path)
        ->addStep(Http::get());

    $start = microtime(true);

    helper_generatorToArray($crawler->run());

    $end = microtime(true);

    $diff = $end - $start;

    expect($diff)->toBeGreaterThan(3.0);

    expect($diff)->toBeLessThan(3.5);
})->with(['/too-many-requests', '/service-unavailable']);

it(
    'starts the first retry after the number of seconds returned in the Retry-After HTTP header',
    function (string $path) {
        $crawler = new RetryErrorResponsesCrawler();

        $crawler
            ->input('http://localhost:8000' . $path . '/retry-after')
            ->addStep(Http::get());

        $start = microtime(true);

        helper_generatorToArray($crawler->run());

        $end = microtime(true);

        $diff = $end - $start;

        expect($diff)->toBeGreaterThan(4.0);

        expect($diff)->toBeLessThan(4.5);
    },
)->with(['/too-many-requests', '/service-unavailable']);

it('goes on crawling when a retry receives a successful response', function (string $path) {
    $crawler = new RetryErrorResponsesCrawler();

    $crawler->input('http://localhost:8000' . $path . '/succeed-on-second-attempt')
        ->addStep(Http::get());

    $start = microtime(true);

    $results = helper_generatorToArray($crawler->run());

    $end = microtime(true);

    $diff = $end - $start;

    expect($results)->toHaveCount(1);

    expect($diff)->toBeGreaterThan(1.0);

    expect($diff)->toBeLessThan(1.5);
})->with(['/too-many-requests', '/service-unavailable']);
