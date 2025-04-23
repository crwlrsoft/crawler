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
        $refinedValue = UrlRefiner::withHost('www.crwlr.software')
            ->addLogger(new CliLogger())
            ->refine($value);

        $logOutput = $this->getActualOutputForAssertion();

        expect($logOutput)
            ->toContain('Refiner UrlRefiner::withHost() can\'t be applied to value of type ' . gettype($value))
            ->and($refinedValue)->toBe($value);
    },
)->with([
    [123],
    [true],
    [new stdClass()],
]);

it('replaces the host in a URL', function (mixed $value, string $expected) {
    expect(UrlRefiner::withHost('www.crwlr.software')->refine($value))->toBe($expected);
})->with([
    ['https://www.example.com/foo', 'https://www.crwlr.software/foo'],
    ['https://www.crwl.io/bar', 'https://www.crwlr.software/bar'],
    [Url::parse('https://www.crwlr.software/baz'), 'https://www.crwlr.software/baz'],
    [Url::parsePsr7('https://crwl.io/quz'), 'https://www.crwlr.software/quz'],
]);

it('refines an array of URLs', function () {
    expect(
        UrlRefiner::withHost('crwl.io')
            ->refine([
                'https://www.example.com/foo',
                'https://www.example.com/bar',
            ]),
    )->toBe(['https://crwl.io/foo', 'https://crwl.io/bar']);
});
