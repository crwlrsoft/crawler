<?php

namespace tests\Steps\Refiners\String;

use Crwlr\Crawler\Logger\CliLogger;
use Crwlr\Crawler\Steps\Refiners\Str;
use PHPUnit\Framework\TestCase;

/** @var TestCase $this */

it('logs a warning and returns the unchanged value when $value is not of type string', function (mixed $value) {
    $refinedValue = Str::afterFirst('foo')
        ->addLogger(new CliLogger())
        ->refine($value);

    $logOutput = $this->getActualOutput();

    expect($logOutput)->toContain('Refiner Str::afterFirst() can\'t be applied to value of type ' . gettype($value));

    expect($refinedValue)->toBe($value);
})->with([123, 12.3, true]);

it('returns the string after first occurrence of another string', function () {
    expect(Str::afterFirst('foo')->refine('yo lo foo boo choo foo gnu'))->toBe('boo choo foo gnu');
});

it('returns the full string if the string to look for is empty', function () {
    expect(Str::afterFirst('')->refine('yo lo foo boo choo'))->toBe('yo lo foo boo choo');
});

it('returns the full string when the string to look for is not contained', function () {
    expect(Str::afterFirst('moo')->refine('yo lo foo boo choo'))->toBe('yo lo foo boo choo');
});
