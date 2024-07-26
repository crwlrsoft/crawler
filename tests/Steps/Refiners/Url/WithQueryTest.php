<?php

namespace tests\Steps\Refiners\Url;

use Crwlr\Crawler\Logger\CliLogger;
use Crwlr\Crawler\Steps\Refiners\UrlRefiner;
use Crwlr\Url\Url;
use PHPUnit\Framework\TestCase;
use stdClass;

/** @var TestCase $this */

it(
    'logs a warning and returns the unchanged value when $value is not a string or instance of UriInterface',
    function (mixed $value) {
        $refinedValue = UrlRefiner::withQuery('a=b&c=d')
            ->addLogger(new CliLogger())
            ->refine($value);

        $logOutput = $this->getActualOutputForAssertion();

        expect($logOutput)
            ->toContain('Refiner UrlRefiner::withQuery() can\'t be applied to value of type ' . gettype($value))
            ->and($refinedValue)->toBe($value);
    },
)->with([
    [123],
    [true],
    [new stdClass()],
]);

it('replaces the query in a URL', function (mixed $value, string $expected) {
    expect(UrlRefiner::withQuery('a=b&c=d')->refine($value))->toBe($expected);
})->with([
    ['https://www.example.com/foo?one=two', 'https://www.example.com/foo?a=b&c=d'],
    ['https://www.example.com/bar', 'https://www.example.com/bar?a=b&c=d'],
    [Url::parse('https://www.crwlr.software/?'), 'https://www.crwlr.software/?a=b&c=d'],
    [Url::parsePsr7('https://www.crwl.io/quz?a=c&b=d'), 'https://www.crwl.io/quz?a=b&c=d'],
]);

it('resets any query', function (mixed $value, string $expected) {
    expect(UrlRefiner::withoutQuery()->refine($value))->toBe($expected);
})->with([
    ['https://www.example.com/foo?one=two', 'https://www.example.com/foo'],
    ['https://www.crwlr.software/?', 'https://www.crwlr.software/'],
]);
