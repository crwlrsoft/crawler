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
        $refinedValue = UrlRefiner::withFragment('foo')
            ->addLogger(new CliLogger())
            ->refine($value);

        $logOutput = $this->getActualOutputForAssertion();

        expect($logOutput)
            ->toContain('Refiner UrlRefiner::withFragment() can\'t be applied to value of type ' . gettype($value))
            ->and($refinedValue)->toBe($value);
    },
)->with([
    [123],
    [true],
    [new stdClass()],
]);

it('replaces the query in a URL', function (mixed $value, string $expected) {
    expect(UrlRefiner::withFragment('#lorem')->refine($value))->toBe($expected);
})->with([
    ['https://www.example.com/path#foo', 'https://www.example.com/path#lorem'],
    ['https://www.example.com/path', 'https://www.example.com/path#lorem'],
    [Url::parse('https://www.crwlr.software/some/path#abc'), 'https://www.crwlr.software/some/path#lorem'],
    [Url::parsePsr7('https://www.crwl.io/quz#'), 'https://www.crwl.io/quz#lorem'],
]);

it('resets any query', function (mixed $value, string $expected) {
    expect(UrlRefiner::withoutFragment()->refine($value))->toBe($expected);
})->with([
    ['https://www.example.com/foo#bar', 'https://www.example.com/foo'],
    ['https://www.crwlr.software/#', 'https://www.crwlr.software/'],
]);

it('refines an array of URLs', function () {
    expect(
        UrlRefiner::withFragment('#lorem')
            ->refine([
                'https://www.example.com/path#foo',
                'https://www.example.com/path#bar',
            ])
    )->toBe(['https://www.example.com/path#lorem', 'https://www.example.com/path#lorem']);
});
