<?php

namespace tests\Steps\Refiners\String;

use Crwlr\Crawler\Logger\CliLogger;
use Crwlr\Crawler\Steps\Refiners\StringRefiner;
use PHPUnit\Framework\TestCase;

/** @var TestCase $this */

it('logs a warning and returns the unchanged value when $value is not of type string', function (mixed $value) {
    $refinedValue = StringRefiner::betweenLast('foo', 'bar')
        ->addLogger(new CliLogger())
        ->refine($value);

    $logOutput = $this->getActualOutputForAssertion();

    expect($logOutput)
        ->toContain('Refiner StringRefiner::betweenLast() can\'t be applied to value of type ' . gettype($value))
        ->and($refinedValue)->toBe($value);
})->with([
    [123],
    [12.3],
    [true],
]);

it('works with an array of strings as value', function () {
    $refinedValue = StringRefiner::betweenLast('foo', 'bar')
        ->addLogger(new CliLogger())
        ->refine(['one foo two bar three foo four bar five', 'six foo seven bar eight foo nine bar ten']);

    expect($refinedValue)->toBe(['four', 'nine']);
});

it('gets the (trimmed) string between the last occurrence of start and the next occurrence of end', function () {
    $refiner = StringRefiner::betweenLast('foo', 'bar');

    $refinedValue = $refiner->refine('bla foo bli bar blu foo ble foo blo bar blö bar blä');

    expect($refinedValue)->toBe('blo');
});

test('if start is an empty string, start from the beginning', function () {
    $refiner = StringRefiner::betweenLast('', 'blu');

    $refinedValue = $refiner->refine('bla foo bli bar blu foo ble foo blo bar blö bar blä');

    expect($refinedValue)->toBe('bla foo bli bar');
});

test('if end is an empty string, it takes the rest of the string until the end', function () {
    $refiner = StringRefiner::betweenLast('blo', '');

    $refinedValue = $refiner->refine('bla foo bli bar blu foo ble foo blo bar blö bar blä');

    expect($refinedValue)->toBe('bar blö bar blä');
});

it('returns an empty string if start is not contained in the string', function () {
    $refiner = StringRefiner::betweenFirst('not contained', '');

    $refinedValue = $refiner->refine('bla foo bli bar blu foo bar asdf foo bar');

    expect($refinedValue)->toBe('');
});
