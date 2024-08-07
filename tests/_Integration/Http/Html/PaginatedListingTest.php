<?php

namespace tests\_Integration\Http\Html;

use Crwlr\Crawler\HttpCrawler;
use Crwlr\Crawler\Loader\LoaderInterface;
use Crwlr\Crawler\Steps\Html;
use Crwlr\Crawler\Steps\Loading\Http;
use Crwlr\Crawler\UserAgents\BotUserAgent;
use Crwlr\Crawler\UserAgents\UserAgentInterface;

use Psr\Log\LoggerInterface;

use function tests\helper_generatorToArray;
use function tests\helper_getFastLoader;

it('paginates through pagination', function () {
    $crawler = new class () extends HttpCrawler {
        protected function userAgent(): UserAgentInterface
        {
            return new BotUserAgent('MyBot');
        }

        public function loader(UserAgentInterface $userAgent, LoggerInterface $logger): LoaderInterface
        {
            return helper_getFastLoader($userAgent, $logger);
        }
    };

    $crawler->input('http://localhost:8000/paginated-listing');

    $crawler
        ->addStep(Http::get()->paginate('#nextPage'))
        ->addStep(Html::getLinks('#listing .item a')->keepAs('url'))
        ->addStep(Http::get())
        ->addStep(
            Html::first('article')
                ->extract(['title' => 'h1', 'number' => '.someNumber'])
                ->keep(),
        );

    $results = helper_generatorToArray($crawler->run());

    expect($results)->toHaveCount(10)
        ->and($results[0]->toArray())->toBe([
            'url' => 'http://localhost:8000/paginated-listing/items/1',
            'title' => 'Some Item 1',
            'number' => '10',
        ])
        ->and($results[9]->toArray())->toBe([
            'url' => 'http://localhost:8000/paginated-listing/items/10',
            'title' => 'Some Item 10',
            'number' => '100',
        ]);
});
