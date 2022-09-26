<?php

namespace tests\Loader\Http\Politeness\TimingUnits;

use Crwlr\Crawler\Loader\Http\Politeness\TimingUnits\Microseconds;

it('can be created from seconds as float', function () {
    expect(Microseconds::fromSeconds(2.0)->value)->toBe(2000000);
});

it('adds the value of another instance to its own value', function () {
    expect(
        Microseconds::fromSeconds(2.23)
            ->add(Microseconds::fromSeconds(3.37))
            ->toSeconds()
    )->toBe(5.6);
});

it('subtracts the value of another instance from its own value', function () {
    expect(
        Microseconds::fromSeconds(5.35)
            ->subtract(Microseconds::fromSeconds(2.20))
            ->toSeconds()
    )->toBe(3.15);
});

it(
    'can tell if the value of another instance is greater than its own',
    function (float $value, float $greaterThan, bool $result) {
        expect(
            Microseconds::fromSeconds($value)
                ->isGreaterThan(Microseconds::fromSeconds($greaterThan))
        )->toBe($result);
    }
)->with([
    [1.2345, 1.2344, true],
    [1.2345, 1.2345, false],
    [1.2345, 1.23, true],
]);

it(
    'can tell if the value of another instance is greater than or equal to its own',
    function (float $value, float $greaterThan, bool $result) {
        expect(
            Microseconds::fromSeconds($value)
                ->isGreaterThanOrEqual(Microseconds::fromSeconds($greaterThan))
        )->toBe($result);
    }
)->with([
    [1.2345, 1.2344, true],
    [1.2345, 1.2345, true],
    [1.2345, 1.23456, false],
]);

it(
    'can tell if the value of another instance is less than its own',
    function (float $value, float $greaterThan, bool $result) {
        expect(
            Microseconds::fromSeconds($value)
                ->isLessThan(Microseconds::fromSeconds($greaterThan))
        )->toBe($result);
    }
)->with([
    [1.2345, 1.2344, false],
    [1.2345, 1.2345, false],
    [1.2345, 1.23456, true],
]);
