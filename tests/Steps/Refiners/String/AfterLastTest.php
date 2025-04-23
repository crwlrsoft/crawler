<?php

namespace tests\Steps\Refiners\String;

use Crwlr\Crawler\Logger\CliLogger;
use Crwlr\Crawler\Steps\Refiners\StringRefiner;
use PHPUnit\Framework\TestCase;

/** @var TestCase $this */

it('logs a warning and returns the unchanged value when $value is not of type string', function (mixed $value) {
    $refinedValue = StringRefiner::afterLast('foo')
        ->addLogger(new CliLogger())
        ->refine($value);

    $logOutput = $this->getActualOutputForAssertion();

    expect($logOutput)
        ->toContain('Refiner StringRefiner::afterLast() can\'t be applied to value of type ' . gettype($value))
        ->and($refinedValue)->toBe($value);
})->with([
    [123],
    [12.3],
    [true],
]);

it('works with an array of strings as value', function () {
    $refinedValue = StringRefiner::afterLast('a')
        ->addLogger(new CliLogger())
        ->refine(['foo a bar a baz', 'lorem a ipsum a dolor']);

    expect($refinedValue)->toBe(['z', 'dolor']);
});

it('returns the string after last occurrence of another string', function () {
    expect(StringRefiner::afterLast('foo')->refine('yo lo foo boo choo foo gnu'))->toBe('gnu');
});

it('returns an empty string if the string to look for is empty', function () {
    expect(StringRefiner::afterLast('')->refine('yo lo foo boo choo'))->toBe('');
});

it('returns the full string when the string to look for is not contained', function () {
    expect(StringRefiner::afterLast('moo')->refine('yo lo foo boo choo'))->toBe('yo lo foo boo choo');
});
