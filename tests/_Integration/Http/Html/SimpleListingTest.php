<?php

namespace tests\_Integration\Http\Html;

use Crwlr\Crawler\HttpCrawler;
use Crwlr\Crawler\Steps\Html;
use Crwlr\Crawler\Steps\Loading\Http;
use Crwlr\Crawler\UserAgents\BotUserAgent;
use Crwlr\Crawler\UserAgents\UserAgentInterface;
use Symfony\Component\Process\Process;
use function tests\helper_generatorToArray;

class TestServerProcess
{
    public static ?Process $process = null;
}

beforeEach(function () {
    if (!isset(TestServerProcess::$process)) {
        TestServerProcess::$process = Process::fromShellCommandline(
            'php -S localhost:8000 ' . __DIR__ . '/../../Server.php'
        );

        TestServerProcess::$process->start();

        usleep(100000);
    }
});

afterAll(function () {
    TestServerProcess::$process?->stop(3, SIGINT);
});

it('gets all the links from a listing and gets data from the detail pages', function () {
    $crawler = new class () extends HttpCrawler {
        protected function userAgent(): UserAgentInterface
        {
            return new BotUserAgent('MyBot');
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
                    'author' => '.articleAuthor'
                ])
                ->addKeysToResult()
        );

    $results = helper_generatorToArray($crawler->run());

    expect($results[0]->toArray())->toBe([
        'title' => 'Some Article 1',
        'date' => '2022-04-13',
        'author' => 'Christian Olear',
    ]);
});
