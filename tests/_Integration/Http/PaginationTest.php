<?php

namespace tests\_Integration\Http;

use Crwlr\Crawler\HttpCrawler;
use Crwlr\Crawler\Loader\LoaderInterface;
use Crwlr\Crawler\Steps\Loading\Http;
use Crwlr\Crawler\UserAgents\UserAgent;
use Crwlr\Crawler\UserAgents\UserAgentInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

use function tests\helper_generatorToArray;
use function tests\helper_getFastLoader;

class PaginationCrawler extends HttpCrawler
{
    protected function userAgent(): UserAgentInterface
    {
        return new UserAgent('PaginationCrawler');
    }

    protected function loader(UserAgentInterface $userAgent, LoggerInterface $logger): LoaderInterface
    {
        return helper_getFastLoader($userAgent, $logger);
    }
}

/** @var TestCase $this */

it('iterates through pagination with the simple website paginator', function () {
    $crawler = new PaginationCrawler();

    $crawler->input('http://localhost:8000/paginated-listing')
        ->addStep(Http::get()->paginate('#pagination'));

    $results = helper_generatorToArray($crawler->run());

    expect($results)->toHaveCount(5);
});

it('only iterates pagination until max pages limit is reached', function () {
    $crawler = new PaginationCrawler();

    $crawler->input('http://localhost:8000/paginated-listing')
        ->addStep(Http::get()->paginate('#pagination', 2));

    $results = helper_generatorToArray($crawler->run());

    expect($results)->toHaveCount(2);

    expect($this->getActualOutputForAssertion())->toContain('Max pages limit reached');
});

it('resets the finished paginating state after each processed (/paginated) input', function () {
    $crawler = new PaginationCrawler();

    $crawler
        ->inputs(['http://localhost:8000/paginated-listing', 'http://localhost:8000/paginated-listing?foo=bar'])
        ->addStep(Http::get()->paginate('#pagination', 2)->outputKey('response'));

    $results = helper_generatorToArray($crawler->run());

    expect($results)->toHaveCount(4);
});
