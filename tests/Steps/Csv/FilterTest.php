<?php

namespace tests\Steps\Csv;

use Crwlr\Crawler\Steps\Csv\Filter;
use Crwlr\Crawler\Steps\FilterRules\Comparison;
use Crwlr\Crawler\Steps\FilterRules\StringCheck;

it('returns false when the colunn doesn\'t exist', function () {
    $row = ['foo' => 'fooValue', 'bar' => 'barValue'];

    expect((new Filter('fooo', Comparison::Equal, 'fooValue'))->matches($row))->toBeFalse();
});

it('correctly filters rows with equal filter', function (bool $expectedResult, array $row) {
    expect((new Filter('baz', Comparison::Equal, 'bazValue'))->matches($row))->toBe($expectedResult);
})->with([
    [true, ['foo' => 'fooValue', 'bar' => 'barValue', 'baz' => 'bazValue']],
    [true, ['foo' => 'fooValue 2', 'bar' => 'barValue 2', 'baz' => 'bazValue']],
    [false, ['foo' => 'fooValue', 'bar' => 'barValue', 'baz' => 'bazValue 2']],
]);

it('correctly filters rows with not equal filter', function (bool $expectedResult, array $row) {
    expect((new Filter('baz', Comparison::NotEqual, 'bazValue'))->matches($row))->toBe($expectedResult);
})->with([
    [false, ['foo' => 'fooValue', 'bar' => 'barValue', 'baz' => 'bazValue']],
    [false, ['foo' => 'fooValue 2', 'bar' => 'barValue 2', 'baz' => 'bazValue']],
    [true, ['foo' => 'fooValue', 'bar' => 'barValue', 'baz' => 'bazValue 2']],
]);

it('correctly filters rows with greater than filter', function (bool $expectedResult, array $row) {
    expect((new Filter('baz', Comparison::GreaterThan, 3))->matches($row))->toBe($expectedResult);
})->with([
    [true, ['foo' => 'fooValue', 'bar' => 'barValue', 'baz' => 4]],
    [true, ['foo' => 'fooValue 2', 'bar' => 'barValue 2', 'baz' => 3.001]],
    [false, ['foo' => 'fooValue', 'bar' => 'barValue', 'baz' => 3]],
    [false, ['foo' => 'fooValue', 'bar' => 'barValue', 'baz' => 2]],
]);

it('correctly filters rows with greater than or equal filter', function (bool $expectedResult, array $row) {
    expect((new Filter('baz', Comparison::GreaterThanOrEqual, 3))->matches($row))->toBe($expectedResult);
})->with([
    [true, ['foo' => 'fooValue', 'bar' => 'barValue', 'baz' => 4]],
    [true, ['foo' => 'fooValue 2', 'bar' => 'barValue 2', 'baz' => 3.001]],
    [true, ['foo' => 'fooValue', 'bar' => 'barValue', 'baz' => 3]],
    [false, ['foo' => 'fooValue', 'bar' => 'barValue', 'baz' => 2]],
]);

it('correctly filters rows with less than filter', function (bool $expectedResult, array $row) {
    expect((new Filter('baz', Comparison::LessThan, 3))->matches($row))->toBe($expectedResult);
})->with([
    [true, ['foo' => 'fooValue', 'bar' => 'barValue', 'baz' => 2]],
    [true, ['foo' => 'fooValue 2', 'bar' => 'barValue 2', 'baz' => 2.999]],
    [false, ['foo' => 'fooValue', 'bar' => 'barValue', 'baz' => 3]],
    [false, ['foo' => 'fooValue', 'bar' => 'barValue', 'baz' => 4]],
]);

it('correctly filters rows with less than or equal filter', function (bool $expectedResult, array $row) {
    expect((new Filter('baz', Comparison::LessThanOrEqual, 3))->matches($row))->toBe($expectedResult);
})->with([
    [true, ['foo' => 'fooValue', 'bar' => 'barValue', 'baz' => 2]],
    [true, ['foo' => 'fooValue 2', 'bar' => 'barValue 2', 'baz' => 2.999]],
    [true, ['foo' => 'fooValue', 'bar' => 'barValue', 'baz' => 3]],
    [false, ['foo' => 'fooValue', 'bar' => 'barValue', 'baz' => 4]],
]);

it('correctly filters rows with string contains filter', function (bool $expectedResult, array $row) {
    expect((new Filter('bar', StringCheck::Contains, 'pew'))->matches($row))->toBe($expectedResult);
})->with([
    [true, ['foo' => 'fooValue', 'bar' => 'pew', 'baz' => 'bazValue']],
    [true, ['foo' => 'fooValue', 'bar' => 'bar pewbar', 'baz' => 'bazValue']],
    [true, ['foo' => 'fooValue', 'bar' => 'bar pew', 'baz' => 'bazValue']],
    [false, ['foo' => 'fooValue', 'bar' => 'barValue', 'baz' => 'bazValue']],
]);

it('correctly filters rows with string starts with filter', function (bool $expectedResult, array $row) {
    expect((new Filter('bar', StringCheck::StartsWith, 'pew'))->matches($row))->toBe($expectedResult);
})->with([
    [true, ['foo' => 'fooValue', 'bar' => 'pew', 'baz' => 'bazValue']],
    [true, ['foo' => 'fooValue', 'bar' => 'pew bar', 'baz' => 'bazValue']],
    [true, ['foo' => 'fooValue', 'bar' => 'pewbar', 'baz' => 'bazValue']],
    [false, ['foo' => 'fooValue', 'bar' => 'bar pew', 'baz' => 'bazValue']],
]);

it('correctly filters rows with string ends with filter', function (bool $expectedResult, array $row) {
    expect((new Filter('bar', StringCheck::EndsWith, 'pew'))->matches($row))->toBe($expectedResult);
})->with([
    [true, ['foo' => 'fooValue', 'bar' => 'pew', 'baz' => 'bazValue']],
    [true, ['foo' => 'fooValue', 'bar' => 'bar pew', 'baz' => 'bazValue']],
    [true, ['foo' => 'fooValue', 'bar' => 'barpew', 'baz' => 'bazValue']],
    [false, ['foo' => 'fooValue', 'bar' => 'pew bar', 'baz' => 'bazValue']],
]);
