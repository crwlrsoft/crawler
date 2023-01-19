<?php

namespace tests\Steps\Refiners\String;

use Crwlr\Crawler\Logger\CliLogger;
use Crwlr\Crawler\Steps\Refiners\Str;
use PHPUnit\Framework\TestCase;

/** @var TestCase $this */

it('logs a warning and returns the unchanged value when $value is not of type string', function (mixed $value) {
    $refinedValue = Str::betweenLast('foo', 'bar')
        ->addLogger(new CliLogger())
        ->refine($value);

    $logOutput = $this->getActualOutput();

    expect($logOutput)->toContain('Refiner Str::betweenLast() can\'t be applied to value of type ' . gettype($value));

    expect($refinedValue)->toBe($value);
})->with([123, 12.3, true]);

it('gets the (trimmed) string between the last occurrence of start and the next occurrence of end', function () {
    $refiner = Str::betweenLast('foo', 'bar');

    $refinedValue = $refiner->refine('bla foo bli bar blu foo ble foo blo bar blö bar blä');

    expect($refinedValue)->toBe('blo');
});

test('if start is an empty string, start from the beginning', function () {
    $refiner = Str::betweenLast('', 'blu');

    $refinedValue = $refiner->refine('bla foo bli bar blu foo ble foo blo bar blö bar blä');

    expect($refinedValue)->toBe('bla foo bli bar');
});

test('if end is an empty string, it takes the rest of the string until the end', function () {
    $refiner = Str::betweenLast('blo', '');

    $refinedValue = $refiner->refine('bla foo bli bar blu foo ble foo blo bar blö bar blä');

    expect($refinedValue)->toBe('bar blö bar blä');
});

it('returns an empty string if start is not contained in the string', function () {
    $refiner = Str::betweenFirst('not contained', '');

    $refinedValue = $refiner->refine('bla foo bli bar blu foo bar asdf foo bar');

    expect($refinedValue)->toBe('');
});
