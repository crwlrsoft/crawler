<?php

namespace tests\Steps\Refiners\String;

use Crwlr\Crawler\Logger\CliLogger;
use Crwlr\Crawler\Steps\Refiners\Str;
use PHPUnit\Framework\TestCase;

/** @var TestCase $this */

it('logs a warning and returns the unchanged value when $value is not of type string', function (mixed $value) {
    $refinedValue = Str::beforeLast('foo')
        ->addLogger(new CliLogger())
        ->refine($value);

    $logOutput = $this->getActualOutput();

    expect($logOutput)->toContain('Refiner Str::beforeLast() can\'t be applied to value of type ' . gettype($value));

    expect($refinedValue)->toBe($value);
})->with([123, 12.3, true]);

it('returns the string before the last occurrence of another string', function () {
    expect(Str::beforeLast('foo')->refine('yo lo foo boo choo foo gnu'))->toBe('yo lo foo boo choo');
});

it('returns the full string if the string to look for is empty', function () {
    expect(Str::beforeLast('')->refine('yo lo foo boo choo'))->toBe('yo lo foo boo choo');
});

it('returns the full string when the string to look for is not contained', function () {
    expect(Str::beforeLast('moo')->refine('yo lo foo boo choo'))->toBe('yo lo foo boo choo');
});
