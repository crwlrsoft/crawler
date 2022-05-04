<?php

namespace tests\Steps\Filters;

use Crwlr\Crawler\Steps\Filters\Enums\StringChecks;
use Crwlr\Crawler\Steps\Filters\StringCheck;
use function tests\helper_getStdClassWithData;

it('checks a string', function () {
    $stringCheck = new StringCheck(StringChecks::Contains, 'bar');

    expect($stringCheck->evaluate('foo bar baz'))->toBeTrue();

    expect($stringCheck->evaluate('lorem ipsum'))->toBeFalse();
});

it('checks a string from an array using a key', function () {
    $stringCheck = new StringCheck(StringChecks::StartsWith, 'waldo');

    $stringCheck->useKey('bar');

    expect($stringCheck->evaluate(['foo' => 'something', 'bar' => 'waldo check', 'baz' => 'test']))->toBeTrue();

    expect($stringCheck->evaluate(['foo' => 'something', 'bar' => 'check waldo', 'baz' => 'test']))->toBeFalse();
});

it('checks a string from an object using a key', function () {
    $stringCheck = new StringCheck(StringChecks::EndsWith, 'waldo');

    $stringCheck->useKey('bar');

    $object = helper_getStdClassWithData(['foo' => 'something', 'bar' => 'check waldo', 'baz' => 'test']);

    expect($stringCheck->evaluate($object))->toBeTrue();

    $object = helper_getStdClassWithData(['foo' => 'something', 'bar' => 'waldo check', 'baz' => 'test']);

    expect($stringCheck->evaluate($object))->toBeFalse();
});
