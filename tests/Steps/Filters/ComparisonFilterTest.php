<?php

namespace tests\Steps\Filters;

use Crwlr\Crawler\Steps\Filters\ComparisonFilter;
use Crwlr\Crawler\Steps\Filters\Enums\ComparisonFilterRule;

use function tests\helper_getStdClassWithData;

it('compares a single value', function () {
    $comparison = new ComparisonFilter(ComparisonFilterRule::GreaterThan, 3);

    expect($comparison->evaluate(4))->toBeTrue()
        ->and($comparison->evaluate(2))->toBeFalse();
});

it('compares a value from an array by key', function () {
    $comparison = new ComparisonFilter(ComparisonFilterRule::NotEqual, 'barValue');

    $comparison->useKey('bar');

    expect($comparison->evaluate(['foo' => 'fooValue', 'bar' => 'barValue']))->toBeFalse()
        ->and($comparison->evaluate(['foo' => 'fooValue', 'bar' => 'barzValue']))->toBeTrue();
});

it('compares a value from an object by key', function () {
    $comparison = new ComparisonFilter(ComparisonFilterRule::NotEqual, 'barValue');

    $comparison->useKey('bar');

    expect($comparison->evaluate(helper_getStdClassWithData(['foo' => 'fooValue', 'bar' => 'barValue'])))->toBeFalse()
        ->and($comparison->evaluate(helper_getStdClassWithData(['foo' => 'fooValue', 'bar' => 'barzValue'])))->toBeTrue();
});
