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
        $refinedValue = UrlRefiner::withPath('/home')
            ->addLogger(new CliLogger())
            ->refine($value);

        $logOutput = $this->getActualOutputForAssertion();

        expect($logOutput)
            ->toContain('Refiner UrlRefiner::withPath() can\'t be applied to value of type ' . gettype($value))
            ->and($refinedValue)->toBe($value);
    },
)->with([
    [123],
    [true],
    [new stdClass()],
]);

it('replaces the path in a URL', function (mixed $value, string $expected) {
    expect(UrlRefiner::withPath('/some/path/123')->refine($value))->toBe($expected);
})->with([
    ['https://www.example.com/foo', 'https://www.example.com/some/path/123'],
    ['https://localhost/yo', 'https://localhost/some/path/123'],
    [Url::parse('https://www.crwlr.software/packages'), 'https://www.crwlr.software/some/path/123'],
    [Url::parsePsr7('https://www.crwl.io/'), 'https://www.crwl.io/some/path/123'],
]);
