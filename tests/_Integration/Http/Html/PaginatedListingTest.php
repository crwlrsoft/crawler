<?php

namespace tests\_Integration\Http\Html;

use Crwlr\Crawler\Crawler;
use Crwlr\Crawler\HttpCrawler;
use Crwlr\Crawler\Steps\Html;
use Crwlr\Crawler\Steps\Loading\Http;
use Crwlr\Crawler\UserAgents\BotUserAgent;
use Crwlr\Crawler\UserAgents\UserAgentInterface;

use function tests\helper_generatorToArray;

it('paginates through pagination', function () {
    $crawler = new class () extends HttpCrawler {
        protected function userAgent(): UserAgentInterface
        {
            return new BotUserAgent('MyBot');
        }
    };

    $crawler->input('http://localhost:8000/paginated-listing');

    $crawler->addStep(
        Crawler::loop(Http::get())
            ->withInput(Html::getLink('#nextPage'))
    );

    $crawler->addStep('url', Html::getLinks('#listing .item a'))
        ->addStep(Http::get())
        ->addStep(
            Html::first('article')
                ->extract(['title' => 'h1', 'number' => '.someNumber'])
                ->addKeysToResult()
        );

    $results = helper_generatorToArray($crawler->run());

    expect($results)->toHaveCount(10);

    expect($results[0]->toArray())->toBe([
        'url' => 'http://localhost:8000/paginated-listing/items/1',
        'title' => 'Some Item 1',
        'number' => '10',
    ]);

    expect($results[9]->toArray())->toBe([
        'url' => 'http://localhost:8000/paginated-listing/items/10',
        'title' => 'Some Item 10',
        'number' => '100',
    ]);
});
