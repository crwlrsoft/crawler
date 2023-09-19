<?php

namespace Crwlr\Crawler\Loader\Http\Politeness\TimingUnits;

use Crwlr\Utils\Microseconds;

class MultipleOf
{
    public function __construct(public readonly float $factor) {}

    public function calc(Microseconds $microseconds): Microseconds
    {
        $factorTwoDecimalsAsInt = (int) (round($this->factor, 2) * 100);

        $result = (int) round(($microseconds->value * $factorTwoDecimalsAsInt) / 100);

        return new Microseconds($result);
    }

    public function factorIsGreaterThan(MultipleOf $multipleOf): bool
    {
        return $this->factor > $multipleOf->factor;
    }
}
