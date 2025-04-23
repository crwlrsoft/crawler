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
        $refinedValue = UrlRefiner::withScheme('https')
            ->addLogger(new CliLogger())
            ->refine($value);

        $logOutput = $this->getActualOutputForAssertion();

        expect($logOutput)
            ->toContain('Refiner UrlRefiner::withScheme() can\'t be applied to value of type ' . gettype($value))
            ->and($refinedValue)->toBe($value);
    },
)->with([
    [123],
    [true],
    [new stdClass()],
]);

it('replaces the scheme in a URL', function (mixed $value, string $expected) {
    expect(UrlRefiner::withScheme('https')->refine($value))->toBe($expected);
})->with([
    ['http://www.example.com/foo', 'https://www.example.com/foo'],
    ['https://www.example.com/foo', 'https://www.example.com/foo'],
    [Url::parse('ftp://www.example.com/bar'), 'https://www.example.com/bar'],
    [Url::parsePsr7('http://www.example.com/baz'), 'https://www.example.com/baz'],
]);

it('refines an array of URLs', function () {
    expect(
        UrlRefiner::withScheme('https')
            ->refine([
                'http://www.example.com/foo',
                'https://www.example.com/bar',
            ]),
    )->toBe(['https://www.example.com/foo', 'https://www.example.com/bar']);
});
