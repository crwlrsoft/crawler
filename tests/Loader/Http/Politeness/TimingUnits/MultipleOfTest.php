<?php

namespace tests\Loader\Http\Politeness\TimingUnits;

use Crwlr\Crawler\Loader\Http\Politeness\TimingUnits\MultipleOf;
use Crwlr\Utils\Microseconds;

it('calculates the multiple of a Microseconds instance', function () {
    expect(
        (new MultipleOf(7.89))
            ->calc(Microseconds::fromSeconds(1.23))
            ->toSeconds()
    )->toBe(9.7047);
});
