<?php

namespace Crwlr\Crawler\Steps\Refiners;

use Crwlr\Crawler\Steps\Refiners\String\StrAfterFirst;
use Crwlr\Crawler\Steps\Refiners\String\StrAfterLast;
use Crwlr\Crawler\Steps\Refiners\String\StrBeforeFirst;
use Crwlr\Crawler\Steps\Refiners\String\StrBeforeLast;
use Crwlr\Crawler\Steps\Refiners\String\StrBetweenFirst;
use Crwlr\Crawler\Steps\Refiners\String\StrBetweenLast;
use Crwlr\Crawler\Steps\Refiners\String\StrReplace;

class StringRefiner
{
    public static function afterFirst(string $first): StrAfterFirst
    {
        return new StrAfterFirst($first);
    }

    public static function afterLast(string $last): StrAfterLast
    {
        return new StrAfterLast($last);
    }

    public static function beforeFirst(string $first): StrBeforeFirst
    {
        return new StrBeforeFirst($first);
    }

    public static function beforeLast(string $last): StrBeforeLast
    {
        return new StrBeforeLast($last);
    }

    public static function betweenFirst(string $start, string $end): StrBetweenFirst
    {
        return new StrBetweenFirst($start, $end);
    }

    public static function betweenLast(string $start, string $end): StrBetweenLast
    {
        return new StrBetweenLast($start, $end);
    }

    /**
     * @param string|string[] $search
     * @param string|string[] $replace
     */
    public static function replace(string|array $search, string|array $replace): StrReplace
    {
        return new StrReplace($search, $replace);
    }
}
