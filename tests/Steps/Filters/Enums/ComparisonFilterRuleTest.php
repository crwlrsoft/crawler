<?php

namespace tests\Steps\Filters\Enums;

use Crwlr\Crawler\Steps\Filters\Enums\ComparisonFilterRule;

it('correctly applies equal operator', function (bool $expectedResult, mixed $value1, mixed $value2) {
    $comparisonFilterRule = ComparisonFilterRule::Equal;

    expect($comparisonFilterRule->evaluate($value1, $value2))->toBe($expectedResult);
})->with([
    [true, 1, 1],
    [true, 'one', 'one'],
    [true, 1.12, 1.12],
    [false, 1, 2],
    [false, 1, '1'],
    [false, 'one', 'two'],
    [false, 1.12, 1.122],
]);

it('correctly applies not equal operator', function (bool $expectedResult, mixed $value1, mixed $value2) {
    $comparisonFilterRule = ComparisonFilterRule::NotEqual;

    expect($comparisonFilterRule->evaluate($value1, $value2))->toBe($expectedResult);
})->with([
    [false, 1, 1],
    [false, 'one', 'one'],
    [false, 1.12, 1.12],
    [true, 1, 2],
    [true, 1, '1'],
    [true, 'one', 'two'],
    [true, 1.12, 1.122],
]);

it('correctly applies greater than operator', function (bool $expectedResult, mixed $value1, mixed $value2) {
    $comparisonFilterRule = ComparisonFilterRule::GreaterThan;

    expect($comparisonFilterRule->evaluate($value1, $value2))->toBe($expectedResult);
})->with([
    [true, 1, 0],
    [true, 12, 3],
    [true, 1.12, 1.11],
    [false, 11, 11],
    [false, 0, 1],
    [false, 3.59, 3.591],
]);

it('correctly applies greater than or equal operator', function (bool $expectedResult, mixed $value1, mixed $value2) {
    $comparisonFilterRule = ComparisonFilterRule::GreaterThanOrEqual;

    expect($comparisonFilterRule->evaluate($value1, $value2))->toBe($expectedResult);
})->with([
    [true, 1, 0],
    [true, 12, 3],
    [true, 1.12, 1.11],
    [true, 11, 11],
    [false, 0, 1],
    [false, 3.59, 3.591],
]);

it('correctly applies less than operator', function (bool $expectedResult, mixed $value1, mixed $value2) {
    $comparisonFilterRule = ComparisonFilterRule::LessThan;

    expect($comparisonFilterRule->evaluate($value1, $value2))->toBe($expectedResult);
})->with([
    [true, 0, 1],
    [true, 4, 5],
    [true, 5.79, 5.7901],
    [false, 11, 11],
    [false, 1, 0],
    [false, 9.2901, 9.29],
]);

it('correctly applies less than or equal operator', function (bool $expectedResult, mixed $value1, mixed $value2) {
    $comparisonFilterRule = ComparisonFilterRule::LessThanOrEqual;

    expect($comparisonFilterRule->evaluate($value1, $value2))->toBe($expectedResult);
})->with([
    [true, 0, 1],
    [true, 4, 5],
    [true, 5.79, 5.7901],
    [true, 11, 11],
    [false, 1, 0],
    [false, 9.2901, 9.29],
]);
