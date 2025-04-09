<?php

namespace Crwlr\Crawler\Steps\Refiners;

use Crwlr\Crawler\Steps\Refiners\DateTime\DateTimeFormat;

class DateTimeRefiner
{
    public static function reformat(string $targetFormat, ?string $originFormat = null): DateTimeFormat
    {
        return new DateTimeFormat($targetFormat, $originFormat);
    }
}
