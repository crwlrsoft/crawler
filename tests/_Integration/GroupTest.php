<?php

namespace tests\_Integration;

use Crwlr\Crawler\Crawler;
use Crwlr\Crawler\HttpCrawler;
use Crwlr\Crawler\Steps\Html;
use Crwlr\Crawler\Steps\Loading\Http;
use Crwlr\Crawler\Steps\Step;
use Crwlr\Crawler\UserAgents\BotUserAgent;
use Crwlr\Crawler\UserAgents\UserAgentInterface;
use Generator;
use function tests\helper_generatorToArray;

class StructuredDataBlogPost extends Step
{
    protected function validateAndSanitizeInput(mixed $input): mixed
    {
        $pageContent = $input->response->getBody()->getContents();

        $input->response->getBody()->rewind();

        return $pageContent;
    }

    protected function invoke(mixed $input): Generator
    {
        $splitAtBeginning = explode('<script type="application/ld+json">', $input);

        foreach ($splitAtBeginning as $key => $snippet) {
            if ($key === 0) {
                continue;
            }

            $jsonBlock = trim(explode('</script>', $snippet)[0]);

            $decoded = json_decode($jsonBlock, true);

            if (!empty($decoded)) {
                yield [
                    'author' => $decoded['author']['name'],
                    'keywords' => $decoded['keywords'],
                ];
            }
        }
    }
}

it('gets both, data from html and the enclosed json-ld using two steps in a group', function () {
    $crawler = new class () extends HttpCrawler {
        protected function userAgent(): UserAgentInterface
        {
            return new BotUserAgent('MyBot');
        }
    };

    $crawler->input('http://localhost:8000/blog-post-with-json-ld');

    $crawler->addStep(Http::get())
        ->addStep(
            Crawler::group()
                ->addStep(
                    Html::first('#content article.blog-post')
                        ->extract(['title' => 'h1', 'date' => '.date'])
                        ->addKeysToResult()
                )
                ->addStep((new StructuredDataBlogPost())->addKeysToResult())
        );

    $result = helper_generatorToArray($crawler->run());

    expect($result[0]->toArray())->toBe([
        'title' => 'Prevent Homograph Attacks using the crwlr/url Package',
        'date' => '2022-01-19',
    ]);

    expect($result[1]->toArray())->toBe([
        'author' => 'Christian Olear',
        'keywords' => 'homograph, attack, security, idn, internationalized domain names, prevention, url, uri',
    ]);
});

it(
    'gets both, data from html and the enclosed json-ld using two steps in a group and combines the results',
    function () {
        $crawler = new class () extends HttpCrawler {
            protected function userAgent(): UserAgentInterface
            {
                return new BotUserAgent('MyBot');
            }
        };

        $crawler->input('http://localhost:8000/blog-post-with-json-ld');

        $crawler->addStep(Http::get())
            ->addStep(
                Crawler::group()
                    ->addStep(
                        Html::first('#content article.blog-post')
                            ->extract(['title' => 'h1', 'date' => '.date'])
                            ->addKeysToResult()
                    )
                    ->addStep((new StructuredDataBlogPost())->addKeysToResult())
                    ->combineToSingleOutput()
            );

        $result = helper_generatorToArray($crawler->run());

        expect($result[0]->toArray())->toBe([
            'title' => 'Prevent Homograph Attacks using the crwlr/url Package',
            'date' => '2022-01-19',
            'author' => 'Christian Olear',
            'keywords' => 'homograph, attack, security, idn, internationalized domain names, prevention, url, uri',
        ]);
    }
);
