<?php

namespace tests\_Integration\Http;

use Crwlr\Crawler\HttpCrawler;
use Crwlr\Crawler\Loader\Http\Messages\RespondedRequest;
use Crwlr\Crawler\Loader\LoaderInterface;
use Crwlr\Crawler\Result;
use Crwlr\Crawler\Steps\Loading\Http;
use Crwlr\Crawler\UserAgents\UserAgent;
use Crwlr\Crawler\UserAgents\UserAgentInterface;
use Psr\Log\LoggerInterface;

use function tests\helper_generatorToArray;
use function tests\helper_getFastLoader;

class GzipCrawler extends HttpCrawler
{
    protected function userAgent(): UserAgentInterface
    {
        return new UserAgent('HelloWorldBot');
    }

    public function loader(UserAgentInterface $userAgent, LoggerInterface $logger): LoaderInterface
    {
        return helper_getFastLoader($userAgent, $logger);
    }
}

it('uncompresses gzip compressed response body when content-type header is sent', function () {
    $crawler = new GzipCrawler();

    $crawler->input('http://localhost:8000/gzip')
        ->addStep('response', Http::get());

    $results = helper_generatorToArray($crawler->run());

    expect($results[0])->toBeInstanceOf(Result::class);

    expect($results[0]->get('response'))->toBeInstanceOf(RespondedRequest::class);

    expect(Http::getBodyString($results[0]->get('response')))->toBe('This is a gzip compressed string');
});
