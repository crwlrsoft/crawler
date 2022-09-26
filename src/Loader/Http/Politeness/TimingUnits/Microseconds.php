<?php

namespace Crwlr\Crawler\Loader\Http\Politeness\TimingUnits;

final class Microseconds
{
    public function __construct(public readonly int $value)
    {
    }

    public static function fromSeconds(float $seconds): self
    {
        return new self((int) ($seconds * 1000000));
    }

    public function add(Microseconds $seconds): Microseconds
    {
        return new Microseconds($this->value + $seconds->value);
    }

    public function subtract(Microseconds $seconds): Microseconds
    {
        return new Microseconds($this->value - $seconds->value);
    }

    public function toSeconds(): float
    {
        return $this->value / 1000000;
    }

    public function equals(Microseconds $seconds): bool
    {
        return $this->value === $seconds->value;
    }

    public function isGreaterThan(Microseconds $seconds): bool
    {
        return $this->value > $seconds->value;
    }

    public function isGreaterThanOrEqual(Microseconds $seconds): bool
    {
        return $this->value >= $seconds->value;
    }

    public function isLessThan(Microseconds $seconds): bool
    {
        return $this->value < $seconds->value;
    }
}
