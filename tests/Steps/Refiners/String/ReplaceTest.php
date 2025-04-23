<?php

namespace tests\Steps\Refiners\String;

use Crwlr\Crawler\Logger\CliLogger;
use Crwlr\Crawler\Steps\Refiners\StringRefiner;
use PHPUnit\Framework\TestCase;

/** @var TestCase $this */

it('logs a warning and returns the unchanged value when $value is not of type string', function (mixed $value) {
    $refinedValue = StringRefiner::replace('foo', 'bar')
        ->addLogger(new CliLogger())
        ->refine($value);

    $logOutput = $this->getActualOutputForAssertion();

    expect($logOutput)
        ->toContain('Refiner StringRefiner::replace() can\'t be applied to value of type ' . gettype($value))
        ->and($refinedValue)->toBe($value);
})->with([
    [123],
    [12.3],
    [true],
]);

it('works when the value is an array of strings', function () {
    $refinedValue = StringRefiner::replace('foo', 'bar')
        ->addLogger(new CliLogger())
        ->refine(['foo boo', 'who foo', 'yo lo']);

    expect($refinedValue)->toBe(['bar boo', 'who bar', 'yo lo']);
});

it('replaces occurrences of a string with another string', function () {
    expect(StringRefiner::replace('foo', 'bar')->refine('foo, test lorem foo yolo'))->toBe('bar, test lorem bar yolo');
});

it('replaces occurrences of an array of strings with another array of strings', function () {
    expect(StringRefiner::replace(['foo', 'bar'], ['yo', 'lo'])->refine('foo bar baz'))->toBe('yo lo baz');
});

it('replaces occurrences of an array of strings with some single string', function () {
    expect(StringRefiner::replace(['foo', 'bar'], '-')->refine('foo bar baz'))->toBe('- - baz');
});
