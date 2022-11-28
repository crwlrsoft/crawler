<?php

namespace tests\Steps\Filters\Enums;

use Crwlr\Crawler\Steps\Filters\Enums\UrlFilterRule;

it('checks if a URL has a certain scheme', function (bool $expectedResult, mixed $haystack, mixed $needle) {
    $urlFilterRule = UrlFilterRule::Scheme;

    expect($urlFilterRule->evaluate($haystack, $needle))->toBe($expectedResult);
})->with([
    [true, 'https://www.example.com', 'https'],
    [true, 'http://www.example.com', 'http'],
    [true, 'ftp://user:password@example.com:21/path', 'ftp'],
    [false, 'https://www.example.com', 'http'],
]);

it('checks if a URL has a certain host', function (bool $expectedResult, mixed $haystack, mixed $needle) {
    $urlFilterRule = UrlFilterRule::Host;

    expect($urlFilterRule->evaluate($haystack, $needle))->toBe($expectedResult);
})->with([
    [true, 'https://www.example.com', 'www.example.com'],
    [true, 'https://jobs.example.com', 'jobs.example.com'],
    [true, 'https://pew.pew.pew.example.com:8080/pew', 'pew.pew.pew.example.com'],
    [false, 'https://jobs.example.com', 'www.example.com'],
]);

it('checks if a URL has a certain domain', function (bool $expectedResult, mixed $haystack, mixed $needle) {
    $urlFilterRule = UrlFilterRule::Domain;

    expect($urlFilterRule->evaluate($haystack, $needle))->toBe($expectedResult);
})->with([
    [true, 'https://www.example.com', 'example.com'],
    [true, 'https://jobs.example.com', 'example.com'],
    [true, 'https://pew.pew.pew.example.com:8080/pew', 'example.com'],
    [false, 'https://www.example.com', 'yolo.com'],
    [false, 'https://www.example.com', 'www.example.com'],
]);

it('checks if a URL has a certain path', function (bool $expectedResult, mixed $haystack, mixed $needle) {
    $urlFilterRule = UrlFilterRule::Path;

    expect($urlFilterRule->evaluate($haystack, $needle))->toBe($expectedResult);
})->with([
    [true, 'https://www.example.com/foo/bar', '/foo/bar'],
    [false, 'https://www.example.com/foo/bar/baz', '/foo/bar'],
]);

it('checks if a URL path starts with a certain path', function (bool $expectedResult, mixed $haystack, mixed $needle) {
    $urlFilterRule = UrlFilterRule::PathStartsWith;

    expect($urlFilterRule->evaluate($haystack, $needle))->toBe($expectedResult);
})->with([
    [true, 'https://www.example.com/foo/bar', '/foo/bar'],
    [true, 'https://www.example.com/foo/bar', '/foo'],
    [false, 'https://www.example.com/foo/bar', '/bar'],
]);

it('checks if a URL path matches a regex pattern', function (bool $expectedResult, mixed $haystack, mixed $needle) {
    $urlFilterRule = UrlFilterRule::PathMatches;

    expect($urlFilterRule->evaluate($haystack, $needle))->toBe($expectedResult);
})->with([
    [true, 'https://www.example.com/foo/bar', '^/foo/'],
    [true, 'https://www.example.com/56/something/foo', '^/\d{1,5}/[a-z]{1,20}'],
    [false, 'https://www.example.com/56/some-thing/foo', '^/\d{1,5}/[a-z]{1,20}/'],
]);
