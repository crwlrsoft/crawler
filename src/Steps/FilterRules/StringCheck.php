<?php

namespace Crwlr\Crawler\Steps\FilterRules;

enum StringCheck
{
    case Contains;

    case StartsWith;

    case EndsWith;

    public function evaluate(string $haystack, string $needle): bool
    {
        return match ($this) {
            self::Contains => str_contains($haystack, $needle),
            self::StartsWith => str_starts_with($haystack, $needle),
            self::EndsWith => str_ends_with($haystack, $needle),
        };
    }
}
