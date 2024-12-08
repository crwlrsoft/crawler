<?php

namespace Crwlr\Crawler\Steps\Filters;

use Exception;
use InvalidArgumentException;

abstract class AbstractFilter implements FilterInterface
{
    protected ?string $useKey = null;

    protected bool|FilterInterface $or = false;

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

        if (is_object($value) && !property_exists($value, $this->useKey) && method_exists($value, '__serialize')) {
            $serialized = $value->__serialize();

            if (array_key_exists($this->useKey, $serialized)) {
                $value = $serialized;
            }
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
