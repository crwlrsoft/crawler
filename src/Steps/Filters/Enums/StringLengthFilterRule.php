<?php

namespace Crwlr\Crawler\Steps\Filters\Enums;

enum StringLengthFilterRule
{
    case Equal;

    case NotEqual;

    case GreaterThan;

    case GreaterThanOrEqual;

    case LessThan;

    case LessThanOrEqual;

    public function evaluate(string $subject, int $compareTo): bool
    {
        $actualStringLength = strlen($subject);

        return match ($this) {
            self::Equal => ($actualStringLength === $compareTo),
            self::NotEqual => ($actualStringLength !== $compareTo),
            self::GreaterThan => ($actualStringLength > $compareTo),
            self::GreaterThanOrEqual => ($actualStringLength >= $compareTo),
            self::LessThan => ($actualStringLength < $compareTo),
            self::LessThanOrEqual => ($actualStringLength <= $compareTo),
        };
    }
}
