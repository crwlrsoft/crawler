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

it('gets all the links from a listing and gets data from the detail pages', function () {
    $crawler = new class extends HttpCrawler {
        protected function userAgent(): UserAgentInterface
        {
            return new BotUserAgent('MyBot');
        }

        public function loader(UserAgentInterface $userAgent, LoggerInterface $logger): LoaderInterface
        {
            return helper_getFastLoader($userAgent, $logger);
        }
    };

    $crawler->input('http://localhost:8000/simple-listing');

    $crawler->addStep(Http::get())
        ->addStep(Html::getLinks('.listingItem a'))
        ->addStep(Http::get())
        ->addStep(
            Html::first('article')
                ->extract([
                    'title' => 'h1',
                    'date' => '.date',
                    'author' => '.articleAuthor',
                ])
                ->keep(),
        );

    $results = helper_generatorToArray($crawler->run());

    expect($results)->toHaveCount(3)
        ->and($results[0]->toArray())->toBe([
            'title' => 'Some Article 1',
            'date' => '2022-04-13',
            'author' => 'Christian Olear',
        ])
        ->and($results[1]->toArray())->toBe([
            'title' => 'Some Article 2',
            'date' => '2022-04-14',
            'author' => 'Christian Olear',
        ])
        ->and($results[2]->toArray())->toBe([
            'title' => 'Some Article 3',
            'date' => '2022-04-15',
            'author' => 'Christian Olear',
        ]);
});
