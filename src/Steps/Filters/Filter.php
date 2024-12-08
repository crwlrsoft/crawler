<?php

namespace Crwlr\Crawler\Steps\Filters;

use Closure;
use Crwlr\Crawler\Steps\Filters\Enums\ComparisonFilterRule;
use Crwlr\Crawler\Steps\Filters\Enums\StringFilterRule;
use Crwlr\Crawler\Steps\Filters\Enums\StringLengthFilterRule;
use Crwlr\Crawler\Steps\Filters\Enums\UrlFilterRule;

abstract class Filter
{
    public static function equal(mixed $equalToValue): ComparisonFilter
    {
        return new ComparisonFilter(ComparisonFilterRule::Equal, $equalToValue);
    }

    public static function notEqual(mixed $notEqualToValue): ComparisonFilter
    {
        return new ComparisonFilter(ComparisonFilterRule::NotEqual, $notEqualToValue);
    }

    public static function greaterThan(mixed $greaterThanValue): ComparisonFilter
    {
        return new ComparisonFilter(ComparisonFilterRule::GreaterThan, $greaterThanValue);
    }

    public static function greaterThanOrEqual(mixed $greaterThanOrEqualValue): ComparisonFilter
    {
        return new ComparisonFilter(ComparisonFilterRule::GreaterThanOrEqual, $greaterThanOrEqualValue);
    }

    public static function lessThan(mixed $lessThanValue): ComparisonFilter
    {
        return new ComparisonFilter(ComparisonFilterRule::LessThan, $lessThanValue);
    }

    public static function lessThanOrEqual(mixed $lessThanOrEqualValue): ComparisonFilter
    {
        return new ComparisonFilter(ComparisonFilterRule::LessThanOrEqual, $lessThanOrEqualValue);
    }

    public static function stringContains(string $containsValue): StringFilter
    {
        return new StringFilter(StringFilterRule::Contains, $containsValue);
    }

    public static function stringStartsWith(string $startsWithValue): StringFilter
    {
        return new StringFilter(StringFilterRule::StartsWith, $startsWithValue);
    }

    public static function stringEndsWith(string $endsWithValue): StringFilter
    {
        return new StringFilter(StringFilterRule::EndsWith, $endsWithValue);
    }

    public static function stringLengthEqual(int $length): StringLengthFilter
    {
        return new StringLengthFilter(StringLengthFilterRule::Equal, $length);
    }

    public static function stringLengthNotEqual(int $length): StringLengthFilter
    {
        return new StringLengthFilter(StringLengthFilterRule::NotEqual, $length);
    }

    public static function stringLengthGreaterThan(int $length): StringLengthFilter
    {
        return new StringLengthFilter(StringLengthFilterRule::GreaterThan, $length);
    }

    public static function stringLengthGreaterThanOrEqual(int $length): StringLengthFilter
    {
        return new StringLengthFilter(StringLengthFilterRule::GreaterThanOrEqual, $length);
    }

    public static function stringLengthLessThan(int $length): StringLengthFilter
    {
        return new StringLengthFilter(StringLengthFilterRule::LessThan, $length);
    }

    public static function stringLengthLessThanOrEqual(int $length): StringLengthFilter
    {
        return new StringLengthFilter(StringLengthFilterRule::LessThanOrEqual, $length);
    }

    public static function urlScheme(string $urlSchemeValue): UrlFilter
    {
        return new UrlFilter(UrlFilterRule::Scheme, $urlSchemeValue);
    }

    public static function urlHost(string $urlHostValue): UrlFilter
    {
        return new UrlFilter(UrlFilterRule::Host, $urlHostValue);
    }

    public static function urlDomain(string $urlDomainValue): UrlFilter
    {
        return new UrlFilter(UrlFilterRule::Domain, $urlDomainValue);
    }

    public static function urlPath(string $urlPathValue): UrlFilter
    {
        return new UrlFilter(UrlFilterRule::Path, $urlPathValue);
    }

    public static function urlPathStartsWith(string $urlPathStartsWithValue): UrlFilter
    {
        return new UrlFilter(UrlFilterRule::PathStartsWith, $urlPathStartsWithValue);
    }

    public static function urlPathMatches(string $urlPathMatchesValue): UrlFilter
    {
        return new UrlFilter(UrlFilterRule::PathMatches, $urlPathMatchesValue);
    }

    public static function arrayHasElement(): ArrayFilter
    {
        return new ArrayFilter();
    }

    public static function custom(Closure $closure): ClosureFilter
    {
        return new ClosureFilter($closure);
    }
}
