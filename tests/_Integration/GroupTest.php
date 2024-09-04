<?php

namespace tests\_Integration;

use Crwlr\Crawler\Crawler;
use Crwlr\Crawler\HttpCrawler;
use Crwlr\Crawler\Loader\LoaderInterface;
use Crwlr\Crawler\Steps\Html;
use Crwlr\Crawler\Steps\Loading\Http;
use Crwlr\Crawler\UserAgents\BotUserAgent;
use Crwlr\Crawler\UserAgents\UserAgentInterface;
use Psr\Log\LoggerInterface;

use function tests\helper_generatorToArray;
use function tests\helper_getFastLoader;

it(
    'gets both, data from html and the enclosed json-ld using two steps in a group and combines the results',
    function () {
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

        $crawler->input('http://localhost:8000/blog-post-with-json-ld');

        $crawler
            ->addStep(Http::get())
            ->addStep(
                Crawler::group()
                    ->addStep(
                        Html::first('#content article.blog-post')
                            ->extract(['title' => 'h1', 'date' => '.date']),
                    )
                    ->addStep(
                        Html::schemaOrg()
                            ->onlyType('BlogPosting')
                            ->extract([
                                'author' => 'author.name',
                                'keywords',
                            ]),
                    )
                    ->keep(),
            );

        $result = helper_generatorToArray($crawler->run());

        expect($result[0]->toArray())->toBe([
            'title' => 'Prevent Homograph Attacks using the crwlr/url Package',
            'date' => '2022-01-19',
            'author' => 'Christian Olear',
            'keywords' => 'homograph, attack, security, idn, internationalized domain names, prevention, url, uri',
        ]);
    },
);
