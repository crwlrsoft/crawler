<?php

namespace tests\Loader\Http\Cache;

use Crwlr\Crawler\Loader\Http\Cache\RetryManager;

it('returns true for status codes >= 400 when nothing else was defined', function (int $statusCode) {
    expect((new RetryManager())->shallBeRetried($statusCode))->toBeTrue();
})->with([[403], [404], [500], [503]]);

it('returns false for status codes below 400 when nothing else was defined', function (int $statusCode) {
    expect((new RetryManager())->shallBeRetried($statusCode))->toBeFalse();
})->with([[100], [200], [302], [308]]);

it(
    'returns true for only one error status code when only() was used with an int',
    function (int $statusCode, bool $expected) {
        $retryManager = new RetryManager();

        $retryManager->only(404);

        expect($retryManager->shallBeRetried($statusCode))->toBe($expected);
    },
)->with([
    [401, false],
    [403, false],
    [404, true],
    [405, false],
    [500, false],
    [503, false],
]);

it(
    'returns true for only a set of error status codes when only() was used with an array',
    function (int $statusCode, bool $expected) {
        $retryManager = new RetryManager();

        $retryManager->only([404, 503]);

        expect($retryManager->shallBeRetried($statusCode))->toBe($expected);
    },
)->with([
    [401, false],
    [403, false],
    [404, true],
    [405, false],
    [500, false],
    [503, true],
]);

it(
    'returns true for all error status codes except one, when except() was used with an int',
    function (int $statusCode, bool $expected) {
        $retryManager = new RetryManager();

        $retryManager->except(404);

        expect($retryManager->shallBeRetried($statusCode))->toBe($expected);
    },
)->with([
    [401, true],
    [403, true],
    [404, false],
    [405, true],
    [500, true],
    [503, true],
]);

it(
    'returns true except for a set of error status codes, when except() was used with an array',
    function (int $statusCode, bool $expected) {
        $retryManager = new RetryManager();

        $retryManager->except([403, 410, 500]);

        expect($retryManager->shallBeRetried($statusCode))->toBe($expected);
    },
)->with([
    [401, true],
    [403, false],
    [404, true],
    [405, true],
    [410, false],
    [500, false],
    [503, true],
]);
