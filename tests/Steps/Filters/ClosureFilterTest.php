<?php

namespace tests\Steps\Filters;

use Crwlr\Crawler\Steps\Filters\ClosureFilter;

use function tests\helper_getStdClassWithData;

it('evaluates with a scalar value', function () {
    $closure = new ClosureFilter(function (mixed $value) {
        return in_array($value, ['one', 'two', 'three'], true);
    });

    expect($closure->evaluate('one'))->toBeTrue();

    expect($closure->evaluate('four'))->toBeFalse();
});

it('evaluates with a value from an array by key', function () {
    $closure = new ClosureFilter(function (mixed $value) {
        return in_array($value, ['one', 'two', 'three'], true);
    });

    $closure->useKey('bar');

    expect($closure->evaluate(['foo' => 'one', 'bar' => 'two']))->toBeTrue();

    expect($closure->evaluate(['foo' => 'three', 'bar' => 'four']))->toBeFalse();
});

it('compares a value from an object by key', function () {
    $closure = new ClosureFilter(function (mixed $value) {
        return in_array($value, ['one', 'two', 'three'], true);
    });

    $closure->useKey('bar');

    expect($closure->evaluate(helper_getStdClassWithData(['foo' => 'one', 'bar' => 'two'])))->toBeTrue();

    expect($closure->evaluate(helper_getStdClassWithData(['foo' => 'three', 'bar' => 'four'])))->toBeFalse();
});
