<?php

namespace tests\_Integration\Http;

use Crwlr\Crawler\HttpCrawler;
use Crwlr\Crawler\Loader\LoaderInterface;
use Crwlr\Crawler\Steps\Dom;
use Crwlr\Crawler\Steps\Html;
use Crwlr\Crawler\Steps\Loading\Http;
use Crwlr\Crawler\UserAgents\UserAgent;
use Crwlr\Crawler\UserAgents\UserAgentInterface;
use Psr\Log\LoggerInterface;

use function tests\helper_generatorToArray;
use function tests\helper_getFastLoader;

class PublisherExampleCrawler extends HttpCrawler
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

test('Http steps can also deal with multiple URLs as one array input', function () {
    $crawler = new PublisherExampleCrawler();

    $crawler
        ->input('http://localhost:8000/publisher/authors')
        ->addStep(Http::get())
        ->addStep(Html::getLinks('#authors a'))
        ->addStep(Http::get())
        ->addStep(
            Html::root()
                ->extract([
                    'name' => 'h1',
                    'age' => '#author-data .age',
                    'bornIn' => '#author-data .born-in',
                    'bookUrls' => Dom::cssSelector('#author-data .books a.book')->attribute('href')->toAbsoluteUrl(),
                ])
                ->addToResult(['name', 'age', 'bornIn'])
        )
        ->addStep(Http::get()->useInputKey('bookUrls'))
        ->addStep(
            Html::root()
                ->extract(['books' => 'h1'])
                ->addToResult()
        );

    $results = helper_generatorToArray($crawler->run());

    expect($results)->toHaveCount(2);

    expect($results[0]->toArray())->toBe([
        'name' => 'John Example',
        'age' => '51',
        'bornIn' => 'Lisbon',
        'books' => ['Some novel', 'Another novel'],
    ]);

    expect($results[1]->toArray())->toBe([
        'name' => 'Susan Example',
        'age' => '49',
        'bornIn' => 'Athens',
        'books' => ['Poems #1', 'Poems #2', 'Poems #3'],
    ]);
});
