<?php

namespace tests\Utils;

use Crwlr\Crawler\Utils\HttpHeaders;

it('normalizes a headers array', function () {
    expect(HttpHeaders::normalize([
        'Accept-Language' => 'de',
        'Accept-Encoding' => ['gzip', 'deflate', 'br'],
    ]))->toBe([
        'Accept-Language' => ['de'],
        'Accept-Encoding' => ['gzip', 'deflate', 'br'],
    ]);
});

it('merges two header arrays', function () {
    $headers = [
        'Accept-Language' => ['de'],
        'Accept-Encoding' => ['gzip', 'deflate', 'br'],
    ];

    $merge = [
        'Accept' => ['text/html', 'application/xhtml+xml', 'application/xml'],
        'Accept-Language' => ['de', 'en'],
    ];

    expect(HttpHeaders::merge($headers, $merge))->toBe([
        'Accept-Language' => ['de', 'en'],
        'Accept-Encoding' => ['gzip', 'deflate', 'br'],
        'Accept' => ['text/html', 'application/xhtml+xml', 'application/xml'],
    ]);
});

it('adds a single value to a certain header in a headers array', function () {
    $headers = ['Accept-Language' => ['de']];

    expect(HttpHeaders::addTo($headers, 'Accept-Language', 'en'))->toBe(['Accept-Language' => ['de', 'en']]);
});

it('adds an array of values to a certain header in a headers array', function () {
    $headers = ['Accept-Language' => ['de']];

    expect(
        HttpHeaders::addTo($headers, 'Accept-Language', ['en-US', 'en'])
    )->toBe(['Accept-Language' => ['de', 'en-US', 'en']]);
});

it('adds the header when calling addTo() with a header name that the array does not contain yet', function () {
    $headers = ['Accept-Encoding' => ['gzip', 'deflate', 'br']];

    expect(
        HttpHeaders::addTo($headers, 'Accept-Language', ['de', 'en'])
    )->toBe([
        'Accept-Encoding' => ['gzip', 'deflate', 'br'],
        'Accept-Language' => ['de', 'en'],
    ]);
});
