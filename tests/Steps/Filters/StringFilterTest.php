<?php

namespace tests\Steps\Filters;

use Crwlr\Crawler\Steps\Filters\Enums\StringFilterRule;
use Crwlr\Crawler\Steps\Filters\StringFilter;

use function tests\helper_getStdClassWithData;

it('checks a string', function () {
    $stringCheck = new StringFilter(StringFilterRule::Contains, 'bar');

    expect($stringCheck->evaluate('foo bar baz'))->toBeTrue();

    expect($stringCheck->evaluate('lorem ipsum'))->toBeFalse();
});

it('checks a string from an array using a key', function () {
    $stringCheck = new StringFilter(StringFilterRule::StartsWith, 'waldo');

    $stringCheck->useKey('bar');

    expect($stringCheck->evaluate(['foo' => 'something', 'bar' => 'waldo check', 'baz' => 'test']))->toBeTrue();

    expect($stringCheck->evaluate(['foo' => 'something', 'bar' => 'check waldo', 'baz' => 'test']))->toBeFalse();
});

it('checks a string from an object using a key', function () {
    $stringCheck = new StringFilter(StringFilterRule::EndsWith, 'waldo');

    $stringCheck->useKey('bar');

    $object = helper_getStdClassWithData(['foo' => 'something', 'bar' => 'check waldo', 'baz' => 'test']);

    expect($stringCheck->evaluate($object))->toBeTrue();

    $object = helper_getStdClassWithData(['foo' => 'something', 'bar' => 'waldo check', 'baz' => 'test']);

    expect($stringCheck->evaluate($object))->toBeFalse();
});
