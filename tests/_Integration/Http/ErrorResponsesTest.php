<?php

namespace tests\_Integration\Http;

use Crwlr\Crawler\HttpCrawler;
use Crwlr\Crawler\Loader\Http\Exceptions\LoadingException;
use Crwlr\Crawler\Loader\LoaderInterface;
use Crwlr\Crawler\Steps\Loading\Http;
use Crwlr\Crawler\UserAgents\UserAgent;
use Crwlr\Crawler\UserAgents\UserAgentInterface;
use Psr\Log\LoggerInterface;

use function tests\helper_generatorToArray;
use function tests\helper_getFastLoader;

class ErrorCrawler extends HttpCrawler
{
    protected function userAgent(): UserAgentInterface
    {
        return new UserAgent('SomeBot');
    }

    public function loader(UserAgentInterface $userAgent, LoggerInterface $logger): LoaderInterface
    {
        return helper_getFastLoader($userAgent, $logger);
    }
}

it('does not yield client error responses by default', function (string $method) {
    $crawler = new ErrorCrawler();

    $crawler->inputs(['http://localhost:8000/client-error-response'])
        ->addStep(Http::{$method}()->keepAs('response'));

    $results = helper_generatorToArray($crawler->run());

    expect($results)->toBeEmpty();
})->with(['get', 'post', 'put', 'patch', 'delete']);

it('does not yield server error responses by default', function (string $method) {
    $crawler = new ErrorCrawler();

    $crawler->inputs(['http://localhost:8000/server-error-response'])
        ->addStep(Http::{$method}()->keepAs('response'));

    $results = helper_generatorToArray($crawler->run());

    expect($results)->toBeEmpty();
})->with(['get', 'post', 'put', 'patch', 'delete']);

it('yields client error responses when yieldErrorResponses() was called', function (string $method) {
    $crawler = new ErrorCrawler();

    $crawler->inputs(['http://localhost:8000/client-error-response'])
        ->addStep(Http::{$method}()->yieldErrorResponses()->keepAs('response'));

    $results = helper_generatorToArray($crawler->run());

    expect($results)->toHaveCount(1);
})->with(['get', 'post', 'put', 'patch', 'delete']);

it('yields server error responses when yieldErrorResponses() was called', function (string $method) {
    $crawler = new ErrorCrawler();

    $crawler->inputs(['http://localhost:8000/server-error-response'])
        ->addStep(Http::{$method}()->yieldErrorResponses()->keepAs('response'));

    $results = helper_generatorToArray($crawler->run());

    expect($results)->toHaveCount(1);
})->with(['get', 'post', 'put', 'patch', 'delete']);

it(
    'goes on crawling after a client error response when stopOnErrorResponse() wasn\'t called',
    function (string $method) {
        $crawler = new ErrorCrawler();

        $crawler->inputs(['http://localhost:8000/client-error-response', 'http://localhost:8000/simple-listing'])
            ->addStep(Http::{$method}()->keepAs('response'));

        $results = helper_generatorToArray($crawler->run());

        expect($results)->toHaveCount(1);
    },
)->with(['get', 'post', 'put', 'patch', 'delete']);

it(
    'goes on crawling after a server error response when stopOnErrorResponse() wasn\'t called',
    function (string $method) {
        $crawler = new ErrorCrawler();

        $crawler->inputs(['http://localhost:8000/server-error-response', 'http://localhost:8000/simple-listing'])
            ->addStep(Http::{$method}()->keepAs('response'));

        $results = helper_generatorToArray($crawler->run());

        expect($results)->toHaveCount(1);
    },
)->with(['get', 'post', 'put', 'patch', 'delete']);

it(
    'stops crawling (throws exception) after a client error response when the stopOnErrorResponse() method was called',
    function (string $method) {
        $crawler = new ErrorCrawler();

        $crawler->inputs(['http://localhost:8000/client-error-response', 'http://localhost:8000/simple-listing'])
            ->addStep(Http::{$method}()->stopOnErrorResponse());

        $crawler->runAndTraverse();
    },
)->with(['get', 'post', 'put', 'patch', 'delete'])->throws(LoadingException::class);

it(
    'stops crawling (throws exception) after a server error response when the stopOnErrorResponse() method was called',
    function (string $method) {
        $crawler = new ErrorCrawler();

        $crawler->inputs(['http://localhost:8000/client-error-response', 'http://localhost:8000/simple-listing'])
            ->addStep(
                Http::{$method}()
                    ->stopOnErrorResponse(),
            );

        $crawler->runAndTraverse();
    },
)->with(['get', 'post', 'put', 'patch', 'delete'])->throws(LoadingException::class);
