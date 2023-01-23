<?php

namespace tests\Steps\Filters\Enums;

use Crwlr\Crawler\Steps\Filters\Enums\StringLengthFilterRule;

it('correctly applies equal rule', function (bool $expectedResult, mixed $value1, mixed $value2) {
    $comparisonFilterRule = StringLengthFilterRule::Equal;

    expect($comparisonFilterRule->evaluate($value1, $value2))->toBe($expectedResult);
})->with([
    [true, 'foo', 3],
    [true, 'lorem', 5],
    [true, 'foo bar', 7],
    [false, 'bar', 4],
    [false, 'baz quz', 6],
]);

it('correctly applies not equal rule', function (bool $expectedResult, mixed $value1, mixed $value2) {
    $comparisonFilterRule = StringLengthFilterRule::NotEqual;

    expect($comparisonFilterRule->evaluate($value1, $value2))->toBe($expectedResult);
})->with([
    [true, 'foo', 2],
    [true, 'foo bar', 8],
    [false, 'foo', 3],
    [false, 'lorem ipsum', 11],
]);

it('correctly applies greater than rule', function (bool $expectedResult, mixed $value1, mixed $value2) {
    $comparisonFilterRule = StringLengthFilterRule::GreaterThan;

    expect($comparisonFilterRule->evaluate($value1, $value2))->toBe($expectedResult);
})->with([
    [true, 'foo', 2],
    [true, 'foo bar', 6],
    [false, 'foo', 3],
    [false, 'foo bar', 7],
]);

it('correctly applies greater than or equal operator', function (bool $expectedResult, mixed $value1, mixed $value2) {
    $comparisonFilterRule = StringLengthFilterRule::GreaterThanOrEqual;

    expect($comparisonFilterRule->evaluate($value1, $value2))->toBe($expectedResult);
})->with([
    [true, 'foo', 2],
    [true, 'foo', 3],
    [true, 'foo bar', 6],
    [true, 'foo bar', 7],
    [false, 'foo', 4],
    [false, 'foo bar', 8],
]);

it('correctly applies less than operator', function (bool $expectedResult, mixed $value1, mixed $value2) {
    $comparisonFilterRule = StringLengthFilterRule::LessThan;

    expect($comparisonFilterRule->evaluate($value1, $value2))->toBe($expectedResult);
})->with([
    [true, 'foo', 4],
    [true, 'foo bar', 8],
    [false, 'foo', 3],
    [false, 'foo bar', 7],
]);

it('correctly applies less than or equal operator', function (bool $expectedResult, mixed $value1, mixed $value2) {
    $comparisonFilterRule = StringLengthFilterRule::LessThanOrEqual;

    expect($comparisonFilterRule->evaluate($value1, $value2))->toBe($expectedResult);
})->with([
    [true, 'foo', 4],
    [true, 'foo', 3],
    [true, 'foo bar', 8],
    [true, 'foo bar', 7],
    [false, 'foo', 2],
    [false, 'foo bar', 6],
]);
