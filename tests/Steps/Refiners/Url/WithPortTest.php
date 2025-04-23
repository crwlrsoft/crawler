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
        $refinedValue = UrlRefiner::withPort(1234)
            ->addLogger(new CliLogger())
            ->refine($value);

        $logOutput = $this->getActualOutputForAssertion();

        expect($logOutput)
            ->toContain('Refiner UrlRefiner::withPort() can\'t be applied to value of type ' . gettype($value))
            ->and($refinedValue)->toBe($value);
    },
)->with([
    [123],
    [true],
    [new stdClass()],
]);

it('replaces the port in a URL', function (mixed $value, string $expected) {
    expect(UrlRefiner::withPort(1234)->refine($value))->toBe($expected);
})->with([
    ['https://www.example.com:8000/foo', 'https://www.example.com:1234/foo'],
    ['https://localhost:8080/yo', 'https://localhost:1234/yo'],
    [Url::parse('https://www.crwlr.software:5678/bar'), 'https://www.crwlr.software:1234/bar'],
    [Url::parsePsr7('https://crwl.io/quz'), 'https://crwl.io:1234/quz'],
]);

it('refines an array of URLs', function () {
    expect(
        UrlRefiner::withPort(1234)
            ->refine([
                'https://www.example.com/foo',
                'https://www.example.com/bar',
            ])
    )->toBe(['https://www.example.com:1234/foo', 'https://www.example.com:1234/bar']);
});
