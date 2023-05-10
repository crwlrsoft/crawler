<?php

namespace tests\_Integration\Http;

use Crwlr\Crawler\Steps\Loading\Http;
use Crwlr\Crawler\Steps\Step;
use Generator;

use function tests\helper_generatorToArray;
use function tests\helper_getFastCrawler;

test('Http steps can receive url, body and headers from an input array', function () {
    $paramsStep = new class () extends Step {
        protected function invoke(mixed $input): Generator
        {
            yield [
                'url' => 'http://localhost:8000/print-headers',
                'body' => 'test',
                'headers' => [
                    'header-x' => 'foo',
                    'header-y' => ['bar'],
                ],
                'header-y' => 'baz',
                'header-z' => ['quz'],
            ];
        }
    };

    $getJsonDataStep = new class () extends Step {
        protected function invoke(mixed $input): Generator
        {
            yield json_decode(Http::getBodyString($input->response), true);
        }
    };

    $crawler = helper_getFastCrawler();

    $crawler
        ->input('anything')
        ->addStep($paramsStep)
        ->addStep(
            Http::get()
                ->useInputKeyAsBody('body')
                ->useInputKeyAsHeaders('headers')
                ->useInputKeyAsHeader('header-y', 'header-y')
                ->useInputKeyAsHeader('header-z', 'header-z')
        )
        ->addStep($getJsonDataStep);

    $results = helper_generatorToArray($crawler->run());

    expect($results)->toHaveCount(1);

    $result = $results[0]->toArray();

    expect($result['Content-Length'])->toBe('4');

    expect($result['header-x'])->toBe('foo');

    expect($result['header-y'])->toBe('bar, baz');

    expect($result['header-z'])->toBe('quz');
});
