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
        $refinedValue = UrlRefiner::withoutPort()
            ->addLogger(new CliLogger())
            ->refine($value);

        $logOutput = $this->getActualOutputForAssertion();

        expect($logOutput)
            ->toContain('Refiner UrlRefiner::withoutPort() can\'t be applied to value of type ' . gettype($value))
            ->and($refinedValue)->toBe($value);
    },
)->with([
    [123],
    [true],
    [new stdClass()],
]);

it('resets the port to null in a URL', function (mixed $value, string $expected) {
    expect(UrlRefiner::withoutPort()->refine($value))->toBe($expected);
})->with([
    ['https://www.example.com:8000/foo', 'https://www.example.com/foo'],
    ['http://localhost:8080/yo', 'http://localhost/yo'],
    [Url::parse('https://www.crwlr.software:5678/bar'), 'https://www.crwlr.software/bar'],
    [Url::parsePsr7('https://crwl.io/quz'), 'https://crwl.io/quz'],
]);

it('refines an array of URLs', function () {
    expect(
        UrlRefiner::withoutPort()
            ->refine([
                'https://www.example.com:8000/foo',
                'https://www.example.com:8080/bar',
            ]),
    )->toBe(['https://www.example.com/foo', 'https://www.example.com/bar']);
});
