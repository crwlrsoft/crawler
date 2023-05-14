<?php

namespace Crwlr\Crawler\Steps\Filters;

use Closure;
use Crwlr\Crawler\Steps\Filters\Enums\ComparisonFilterRule;
use Crwlr\Crawler\Steps\Filters\Enums\StringFilterRule;
use Crwlr\Crawler\Steps\Filters\Enums\StringLengthFilterRule;
use Crwlr\Crawler\Steps\Filters\Enums\UrlFilterRule;
use Exception;
use InvalidArgumentException;

abstract class Filter implements FilterInterface
{
    protected ?string $useKey = null;

    protected bool|FilterInterface $or = false;

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

    public static function custom(Closure $closure): ClosureFilter
    {
        return new ClosureFilter($closure);
    }

    public function useKey(string $key): static
    {
        $this->useKey = $key;

        return $this;
    }

    /**
     * Step::orWhere() uses this method to link further Filters with OR to this filter.
     * The Step then takes care of checking if one of the ORs evaluates to true.
     */
    public function addOr(FilterInterface $filter): void
    {
        if ($this->or instanceof FilterInterface) {
            $or = $this->or;

            while ($or->getOr()) {
                $or = $or->getOr();
            }

            $or->addOr($filter);
        } else {
            $this->or = $filter;
        }
    }

    /**
     * Get the Filter linked to this Filter as OR.
     */
    public function getOr(): ?FilterInterface
    {
        return $this->or instanceof FilterInterface ? $this->or : null;
    }

    public function negate(): NegatedFilter
    {
        return new NegatedFilter($this);
    }

    /**
     * @throws Exception
     */
    protected function getKey(mixed $value): mixed
    {
        if ($this->useKey === null) {
            return $value;
        }

        if (!is_array($value) && !is_object($value)) {
            throw new InvalidArgumentException('Can only filter by key with array or object output.');
        }

        if (
            (is_array($value) && !array_key_exists($this->useKey, $value)) ||
            (is_object($value) && !property_exists($value, $this->useKey))
        ) {
            throw new Exception('Key to filter by does not exist in output.');
        }

        return is_array($value) ? $value[$this->useKey] : $value->{$this->useKey};
    }
}
