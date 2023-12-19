<?php

namespace tests\_Integration\Http;

use Crwlr\Crawler\HttpCrawler;
use Crwlr\Crawler\Loader\LoaderInterface;
use Crwlr\Crawler\Steps\Loading\Http;
use Crwlr\Crawler\Steps\Loading\Http\Paginator;
use Crwlr\Crawler\Steps\Loading\Http\Paginators\QueryParamsPaginator;
use Crwlr\Crawler\Steps\Loading\Http\Paginators\StopRules\PaginatorStopRules;
use Crwlr\Crawler\UserAgents\UserAgent;
use Crwlr\Crawler\UserAgents\UserAgentInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

use function tests\helper_generatorToArray;
use function tests\helper_getFastLoader;

class QueryParamPaginationCrawler extends HttpCrawler
{
    protected function userAgent(): UserAgentInterface
    {
        return new UserAgent('QueryParamPaginationCrawler');
    }

    protected function loader(UserAgentInterface $userAgent, LoggerInterface $logger): LoaderInterface
    {
        return helper_getFastLoader($userAgent, $logger);
    }
}

/** @var TestCase $this */

it('paginates using query params sent in the request body', function () {
    $crawler = new QueryParamPaginationCrawler();

    $crawler
        ->input('http://localhost:8000/query-param-pagination')
        ->addStep(
            Http::post(body: 'page=1')
                ->paginate(
                    Paginator::queryParams(5)
                        ->inBody()
                        ->increase('page')
                        ->stopWhen(PaginatorStopRules::isEmptyInJson('data.items'))
                )->addToResult(['body'])
        );

    $results = helper_generatorToArray($crawler->run());

    expect($results)->toHaveCount(4);
});

it('paginates using URL query params', function () {
    $crawler = new QueryParamPaginationCrawler();

    $crawler
        ->input('http://localhost:8000/query-param-pagination?page=1')
        ->addStep(
            Http::get()
                ->paginate(
                    Paginator::queryParams(5)
                        ->inUrl()
                        ->increase('page')
                        ->stopWhen(PaginatorStopRules::isEmptyInJson('data.items'))
                )->addToResult(['body'])
        );

    $results = helper_generatorToArray($crawler->run());

    expect($results)->toHaveCount(4);
});

it('paginates only until the max pages limit', function () {
    $crawler = new QueryParamPaginationCrawler();

    $crawler
        ->input('http://localhost:8000/query-param-pagination?page=1')
        ->addStep(
            Http::get()
                ->paginate(
                    QueryParamsPaginator::paramsInUrl(2)
                        ->increase('page')
                        ->stopWhen(PaginatorStopRules::isEmptyInJson('data.items'))
                )->addToResult(['body'])
        );

    $results = helper_generatorToArray($crawler->run());

    expect($results)->toHaveCount(2);
});

it('resets the finished paginating state after each processed (/paginated) input', function () {
    $crawler = new QueryParamPaginationCrawler();

    $crawler
        ->inputs([
            'http://localhost:8000/query-param-pagination?page=1',
            'http://localhost:8000/query-param-pagination?page=1&foo=bar',
        ])
        ->addStep(
            Http::get()
                ->paginate(
                    QueryParamsPaginator::paramsInUrl(2)
                        ->increase('page')
                        ->stopWhen(PaginatorStopRules::isEmptyInJson('data.items'))
                )->addToResult(['body'])
        );

    $results = helper_generatorToArray($crawler->run());

    expect($results)->toHaveCount(4);
});
