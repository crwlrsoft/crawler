<?php

namespace tests\Steps\FilterRules;

use Crwlr\Crawler\Steps\FilterRules\StringCheck;

it('checks if a string contains another string', function (
    bool $expectedResult,
    mixed $haystack,
    mixed $needle,
) {
    $comparison = StringCheck::Contains;

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
    $comparison = StringCheck::StartsWith;

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
    $comparison = StringCheck::EndsWith;

    expect($comparison->evaluate($haystack, $needle))->toBe($expectedResult);
})->with([
    [true, 'foobarbaz', 'baz'],
    [true, 'foo bar baz', 'baz'],
    [true, 'foo bar baz', 'bar baz'],
    [false, 'foo bar baz', 'bar'],
    [false, 'foo bar baz', 'foo'],
    [false, 'foo bar baz', 'Baz'],
]);
