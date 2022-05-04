<?php

namespace tests\Steps\Filters\Enums;

use Crwlr\Crawler\Steps\Filters\Enums\StringChecks;

it('checks if a string contains another string', function (
    bool $expectedResult,
    mixed $haystack,
    mixed $needle,
) {
    $comparison = StringChecks::Contains;

    expect($comparison->evaluate($haystack, $needle))->toBe($expectedResult);
})->with([
    [true, 'foobarbaz', 'foo'],
    [true, 'foo bar baz', 'foo'],
    [true, 'foo bar baz', 'bar'],
    [true, 'foo bar baz', 'baz'],
    [false, 'foo bar baz', 'Foo'],
]);

it('checks if a string starts with another string', function (
    bool $expectedResult,
    mixed $haystack,
    mixed $needle,
) {
    $comparison = StringChecks::StartsWith;

    expect($comparison->evaluate($haystack, $needle))->toBe($expectedResult);
})->with([
    [true, 'foobarbaz', 'foo'],
    [true, 'foo bar baz', 'foo'],
    [true, 'foo bar baz', 'foo bar'],
    [false, 'foo bar baz', 'bar'],
    [false, 'foo bar baz', 'baz'],
    [false, 'foo bar baz', 'Foo'],
]);

it('checks if a string ends with another string', function (
    bool $expectedResult,
    mixed $haystack,
    mixed $needle,
) {
    $comparison = StringChecks::EndsWith;

    expect($comparison->evaluate($haystack, $needle))->toBe($expectedResult);
})->with([
    [true, 'foobarbaz', 'baz'],
    [true, 'foo bar baz', 'baz'],
    [true, 'foo bar baz', 'bar baz'],
    [false, 'foo bar baz', 'bar'],
    [false, 'foo bar baz', 'foo'],
    [false, 'foo bar baz', 'Baz'],
]);
