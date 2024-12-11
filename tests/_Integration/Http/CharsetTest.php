<?php

namespace tests\_Integration\Http;

use Crwlr\Crawler\HttpCrawler;
use Crwlr\Crawler\Loader\LoaderInterface;
use Crwlr\Crawler\Steps\Html;
use Crwlr\Crawler\Steps\Loading\Http;
use Crwlr\Crawler\UserAgents\UserAgent;
use Crwlr\Crawler\UserAgents\UserAgentInterface;
use Psr\Log\LoggerInterface;

use function tests\helper_generatorToArray;
use function tests\helper_getFastLoader;

class CharsetExampleCrawler extends HttpCrawler
{
    public function loader(UserAgentInterface $userAgent, LoggerInterface $logger): LoaderInterface
    {
        return helper_getFastLoader($userAgent, $logger);
    }

    protected function userAgent(): UserAgentInterface
    {
        return new UserAgent('SomeUserAgent');
    }
}

it('Fixes non UTF-8 characters in HTML documents declared as UTF-8', function () {
    $crawler = new CharsetExampleCrawler();

    $crawler
        ->input('http://localhost:8000/non-utf-8-charset')
        ->addStep(Http::get())
        ->addStep(Html::root()->extract(['foo' => '.element']));

    $results = helper_generatorToArray($crawler->run());

    expect($results)->toHaveCount(1)
        ->and($results[0]->toArray())->toBe(['foo' => '0 l/m²']);
});
