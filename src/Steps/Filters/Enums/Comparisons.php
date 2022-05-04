<?php

namespace Crwlr\Crawler\Steps\Filters\Enums;

enum Comparisons
{
    case Equal;

    case NotEqual;

    case GreaterThan;

    case GreaterThanOrEqual;

    case LessThan;

    case LessThanOrEqual;

    public function evaluate(mixed $value, mixed $compareTo): bool
    {
        return match ($this) {
            self::Equal => ($value === $compareTo),
            self::NotEqual => ($value !== $compareTo),
            self::GreaterThan => ($value > $compareTo),
            self::GreaterThanOrEqual => ($value >= $compareTo),
            self::LessThan => ($value < $compareTo),
            self::LessThanOrEqual => ($value <= $compareTo),
        };
    }
}
