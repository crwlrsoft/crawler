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

    expect($results)->toHaveCount(2)
        ->and($results[0]->toArray())->toBe([
            'name' => 'John Example',
            'age' => '51',
            'bornIn' => 'Lisbon',
            'books' => ['Some novel', 'Another novel'],
        ])
        ->and($results[1]->toArray())->toBe([
            'name' => 'Susan Example',
            'age' => '49',
            'bornIn' => 'Athens',
            'books' => ['Poems #1', 'Poems #2', 'Poems #3'],
        ]);

});

it('turns an array of URLs to nested extracted data from those child pages using sub crawlers', function () {
    $crawlerBuilder = new class () {
        public function build(): \Crwlr\Crawler\Crawler
        {
            $crawler = new PublisherExampleCrawler();

            return $crawler
                ->input('http://localhost:8000/publisher/authors')
                ->addStep(Http::get())
                ->addStep(Html::getLinks('#authors a'))
                ->addStep(Http::get())
                ->addStep($this->extractAuthorData());
        }

        private function extractAuthorData(): Html
        {
            return Html::root()
                ->extract([
                    'name' => 'h1',
                    'age' => '#author-data .age',
                    'bornIn' => '#author-data .born-in',
                    'books' => Dom::cssSelector('#author-data .books a.book')->link(),
                ])
                ->subCrawlerFor('books', function (\Crwlr\Crawler\Crawler $crawler) {
                    return $crawler
                        ->addStep(Http::get())
                        ->addStep(
                            $this->extractBookData()
                        );
                });
        }

        private function extractBookData(): Html
        {
            return Html::root()
                ->extract(['title' => 'h1', 'editions' => Dom::cssSelector('#editions a')->link()])
                ->subCrawlerFor('editions', function (\Crwlr\Crawler\Crawler $crawler) {
                    return $crawler
                        ->addStep(Http::get())
                        ->addStep($this->extractEditionData());
                });
        }

        private function extractEditionData(): Html
        {
            return Html::root()
                ->extract(['year' => '.year', 'publisher' => '.publishingCompany']);
        }
    };

    $results = helper_generatorToArray($crawlerBuilder->build()->run());

    expect($results)->toHaveCount(2)
        ->and($results[0]->toArray())->toBe([
            'name' => 'John Example',
            'age' => '51',
            'bornIn' => 'Lisbon',
            'books' => [
                [
                    'title' => 'Some novel',
                    'editions' => [
                        ['year' => '1996', 'publisher' => 'Foo'],
                        ['year' => '2005', 'publisher' => 'Foo'],
                    ]
                ],
                [
                    'title' => 'Another novel',
                    'editions' => [
                        ['year' => '2001', 'publisher' => 'Foo'],
                        ['year' => '2009', 'publisher' => 'Bar'],
                        ['year' => '2017', 'publisher' => 'Bar'],
                    ]
                ],
            ],
        ])
        ->and($results[1]->toArray())->toBe([
            'name' => 'Susan Example',
            'age' => '49',
            'bornIn' => 'Athens',
            'books' => [
                [
                    'title' => 'Poems #1',
                    'editions' => [
                        ['year' => '2008', 'publisher' => 'Poems'],
                        ['year' => '2009', 'publisher' => 'Poems'],
                    ]
                ],
                [
                    'title' => 'Poems #2',
                    'editions' => [
                        ['year' => '2011', 'publisher' => 'Poems'],
                        ['year' => '2014', 'publisher' => 'New Poems'],
                    ]
                ],
                [
                    'title' => 'Poems #3',
                    'editions' => [
                        ['year' => '2013', 'publisher' => 'Poems'],
                        ['year' => '2017', 'publisher' => 'New Poems'],
                    ]
                ],
            ],
        ]);
});

test('it can also keep the URLs, provided to the sub crawler', function () {
    $crawlerBuilder = new class () {
        public function build(): \Crwlr\Crawler\Crawler
        {
            $crawler = new PublisherExampleCrawler();

            return $crawler
                ->input('http://localhost:8000/publisher/authors')
                ->addStep(Http::get())
                ->addStep(Html::getLinks('#authors a'))
                ->addStep(Http::get())
                ->addStep($this->extractAuthorData());
        }

        private function extractAuthorData(): Html
        {
            return Html::root()
                ->extract([
                    'name' => 'h1',
                    'age' => '#author-data .age',
                    'bornIn' => '#author-data .born-in',
                    'books' => Dom::cssSelector('#author-data .books a.book')->link(),
                ])
                ->subCrawlerFor('books', function (\Crwlr\Crawler\Crawler $crawler) {
                    return $crawler
                        ->addStep(Http::get()->keepInputAs('url'))
                        ->addStep($this->extractBookData());
                });
        }

        private function extractBookData(): Html
        {
            return Html::root()
                ->extract(['title' => 'h1', 'editions' => Dom::cssSelector('#editions a')->link()])
                ->subCrawlerFor('editions', function (\Crwlr\Crawler\Crawler $crawler) {
                    return $crawler
                        ->addStep(Http::get()->keepInputAs('url'))
                        ->addStep($this->extractEditionData());
                });
        }

        private function extractEditionData(): Html
        {
            return Html::root()
                ->extract(['year' => '.year', 'publisher' => '.publishingCompany']);
        }
    };

    $results = helper_generatorToArray($crawlerBuilder->build()->run());

    expect($results)->toHaveCount(2)
        ->and($results[0]->toArray())->toBe([
            'name' => 'John Example',
            'age' => '51',
            'bornIn' => 'Lisbon',
            'books' => [
                [
                    'url' => 'http://localhost:8000/publisher/books/1',
                    'title' => 'Some novel',
                    'editions' => [
                        [
                            'url' => 'http://localhost:8000/publisher/books/1/edition/1',
                            'year' => '1996',
                            'publisher' => 'Foo',
                        ],
                        [
                            'url' => 'http://localhost:8000/publisher/books/1/edition/2',
                            'year' => '2005',
                            'publisher' => 'Foo',
                        ],
                    ]
                ],
                [
                    'url' => 'http://localhost:8000/publisher/books/2',
                    'title' => 'Another novel',
                    'editions' => [
                        [
                            'url' => 'http://localhost:8000/publisher/books/2/edition/1',
                            'year' => '2001',
                            'publisher' => 'Foo',
                        ],
                        [
                            'url' => 'http://localhost:8000/publisher/books/2/edition/2',
                            'year' => '2009',
                            'publisher' => 'Bar',
                        ],
                        [
                            'url' => 'http://localhost:8000/publisher/books/2/edition/3',
                            'year' => '2017',
                            'publisher' => 'Bar',
                        ],
                    ]
                ],
            ],
        ])
        ->and($results[1]->toArray())->toBe([
            'name' => 'Susan Example',
            'age' => '49',
            'bornIn' => 'Athens',
            'books' => [
                [
                    'url' => 'http://localhost:8000/publisher/books/3',
                    'title' => 'Poems #1',
                    'editions' => [
                        [
                            'url' => 'http://localhost:8000/publisher/books/3/edition/1',
                            'year' => '2008',
                            'publisher' => 'Poems',
                        ],
                        [
                            'url' => 'http://localhost:8000/publisher/books/3/edition/2',
                            'year' => '2009',
                            'publisher' => 'Poems',
                        ],
                    ]
                ],
                [
                    'url' => 'http://localhost:8000/publisher/books/4',
                    'title' => 'Poems #2',
                    'editions' => [
                        [
                            'url' => 'http://localhost:8000/publisher/books/4/edition/1',
                            'year' => '2011',
                            'publisher' => 'Poems',
                        ],
                        [
                            'url' => 'http://localhost:8000/publisher/books/4/edition/2',
                            'year' => '2014',
                            'publisher' => 'New Poems',
                        ],
                    ]
                ],
                [
                    'url' => 'http://localhost:8000/publisher/books/5',
                    'title' => 'Poems #3',
                    'editions' => [
                        [
                            'url' => 'http://localhost:8000/publisher/books/5/edition/1',
                            'year' => '2013',
                            'publisher' => 'Poems',
                        ],
                        [
                            'url' => 'http://localhost:8000/publisher/books/5/edition/2',
                            'year' => '2017',
                            'publisher' => 'New Poems',
                        ],
                    ]
                ],
            ],
        ]);
});
