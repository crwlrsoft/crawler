<?php

namespace tests\Steps\Filters;

use Crwlr\Crawler\Steps\Filters\Enums\StringLengthFilterRule;
use Crwlr\Crawler\Steps\Filters\StringLengthFilter;

use function tests\helper_getStdClassWithData;

it('checks a string', function () {
    $stringCheck = new StringLengthFilter(StringLengthFilterRule::GreaterThan, 10);

    expect($stringCheck->evaluate('foo'))->toBeFalse();

    expect($stringCheck->evaluate('lorem ipsum'))->toBeTrue();
});

it('checks a string from an array using a key', function () {
    $stringCheck = new StringLengthFilter(StringLengthFilterRule::GreaterThan, 10);

    $stringCheck->useKey('bar');

    expect($stringCheck->evaluate(['foo' => 'one', 'bar' => 'two', 'baz' => 'three']))->toBeFalse();

    expect($stringCheck->evaluate(['foo' => 'one', 'bar' => 'lorem ipsum', 'baz' => 'three']))->toBeTrue();
});

it('checks a string from an object using a key', function () {
    $stringCheck = new StringLengthFilter(StringLengthFilterRule::GreaterThan, 10);

    $stringCheck->useKey('bar');

    $object = helper_getStdClassWithData(['foo' => 'one', 'bar' => 'two', 'baz' => 'three']);

    expect($stringCheck->evaluate($object))->toBeFalse();

    $object = helper_getStdClassWithData(['foo' => 'one', 'bar' => 'lorem ipsum', 'baz' => 'three']);

    expect($stringCheck->evaluate($object))->toBeTrue();
});
