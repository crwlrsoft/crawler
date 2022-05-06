<?php

namespace Crwlr\Crawler\Steps\Filters;

use Crwlr\Crawler\Steps\Filters\Enums\Comparisons;
use Crwlr\Crawler\Steps\Filters\Enums\StringChecks;
use Crwlr\Crawler\Steps\Filters\Enums\UrlFilterRule;
use Exception;
use InvalidArgumentException;

abstract class Filter implements FilterInterface
{
    protected ?string $useKey = null;

    protected bool|FilterInterface $or = false;

    public static function equal(mixed $equalToValue): Comparison
    {
        return new Comparison(Comparisons::Equal, $equalToValue);
    }

    public static function notEqual(mixed $notEqualToValue): Comparison
    {
        return new Comparison(Comparisons::NotEqual, $notEqualToValue);
    }

    public static function greaterThan(mixed $greaterThanValue): Comparison
    {
        return new Comparison(Comparisons::GreaterThan, $greaterThanValue);
    }

    public static function greaterThanOrEqual(mixed $greaterThanOrEqualValue): Comparison
    {
        return new Comparison(Comparisons::GreaterThanOrEqual, $greaterThanOrEqualValue);
    }

    public static function lessThan(mixed $lessThanValue): Comparison
    {
        return new Comparison(Comparisons::LessThan, $lessThanValue);
    }

    public static function lessThanOrEqual(mixed $lessThanOrEqualValue): Comparison
    {
        return new Comparison(Comparisons::LessThanOrEqual, $lessThanOrEqualValue);
    }

    public static function stringContains(mixed $containsValue): StringCheck
    {
        return new StringCheck(StringChecks::Contains, $containsValue);
    }

    public static function stringStartsWith(mixed $startsWithValue): StringCheck
    {
        return new StringCheck(StringChecks::StartsWith, $startsWithValue);
    }

    public static function stringEndsWith(mixed $endsWithValue): StringCheck
    {
        return new StringCheck(StringChecks::EndsWith, $endsWithValue);
    }

    public static function urlScheme(mixed $urlSchemeValue): UrlFilter
    {
        return new UrlFilter(UrlFilterRule::Scheme, $urlSchemeValue);
    }

    public static function urlHost(mixed $urlHostValue): UrlFilter
    {
        return new UrlFilter(UrlFilterRule::Host, $urlHostValue);
    }

    public static function urlDomain(mixed $urlDomainValue): UrlFilter
    {
        return new UrlFilter(UrlFilterRule::Domain, $urlDomainValue);
    }

    public static function urlPath(mixed $urlPathValue): UrlFilter
    {
        return new UrlFilter(UrlFilterRule::Path, $urlPathValue);
    }

    public static function urlPathStartsWith(mixed $urlPathStartsWithValue): UrlFilter
    {
        return new UrlFilter(UrlFilterRule::PathStartsWith, $urlPathStartsWithValue);
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

    protected function getKey(mixed $value): mixed
    {
        if ($this->useKey === null) {
            return $value;
        }

        if (!is_array($value) && !is_object($value)) {
            throw new InvalidArgumentException('Can only filter by key with array or object output.');
        }

        if (
            (is_array($value) && !isset($value[$this->useKey])) ||
            (is_object($value) && !property_exists($value, $this->useKey))
        ) {
            throw new Exception('Key to filter by does not exist in output.');
        }

        return is_array($value) ? $value[$this->useKey] : $value->{$this->useKey};
    }
}
