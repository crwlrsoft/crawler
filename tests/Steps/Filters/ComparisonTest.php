<?php

namespace tests\Steps\Filters;

use Crwlr\Crawler\Steps\Filters\Comparison;
use Crwlr\Crawler\Steps\Filters\Enums\Comparisons;
use function tests\helper_getStdClassWithData;

it('compares a single value', function () {
    $comparison = new Comparison(Comparisons::GreaterThan, 3);

    expect($comparison->evaluate(4))->toBeTrue();

    expect($comparison->evaluate(2))->toBeFalse();
});

it('compares a value from an array by key', function () {
    $comparison = new Comparison(Comparisons::NotEqual, 'barValue');

    $comparison->useKey('bar');

    expect($comparison->evaluate(['foo' => 'fooValue', 'bar' => 'barValue']))->toBeFalse();

    expect($comparison->evaluate(['foo' => 'fooValue', 'bar' => 'barzValue']))->toBeTrue();
});

it('compares a value from an object by key', function () {
    $comparison = new Comparison(Comparisons::NotEqual, 'barValue');

    $comparison->useKey('bar');

    expect($comparison->evaluate(helper_getStdClassWithData(['foo' => 'fooValue', 'bar' => 'barValue'])))->toBeFalse();

    expect($comparison->evaluate(helper_getStdClassWithData(['foo' => 'fooValue', 'bar' => 'barzValue'])))->toBeTrue();
});
