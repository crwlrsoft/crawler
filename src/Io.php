<?php

namespace Crwlr\Crawler;

class Io
{
    protected string|int|float|bool|null $key = null;

    public function __construct(
        protected mixed $value,
        public ?Result $result = null,
        public ?Result $addLaterToResult = null,
    ) {
        if ($value instanceof self) {
            $this->value = $value->value;

            $this->result ??= $value->result;

            $this->addLaterToResult ??= $value->addLaterToResult;
        }
    }

    public function get(): mixed
    {
        return $this->value;
    }

    /**
     * Sets and returns a key to use as identifier
     *
     * To only get unique results from a step use the key this method creates for comparison.
     * In case the output values are arrays or objects and contain a unique identifier that can be used, provide that
     * key name, so it doesn't need to create a key from the whole array/object.
     */
    public function setKey(?string $useFromValue = null): string|int|float|bool|null
    {
        if ($useFromValue && is_array($this->value) && array_key_exists($useFromValue, $this->value)) {
            $this->key = $this->valueToString($this->value[$useFromValue]);
        } elseif ($useFromValue && is_object($this->value) && property_exists($this->value, $useFromValue)) {
            $this->key = $this->valueToString($this->value->{$useFromValue});
        } else {
            $this->key = $this->valueToString($this->value);
        }

        return $this->key;
    }

    public function getKey(): string|int|float|bool|null
    {
        if ($this->key === null) {
            $this->setKey();
        }

        return $this->key;
    }

    public function isArrayWithStringKeys(): bool
    {
        if (!is_array($this->value)) {
            return false;
        }

        foreach ($this->value as $key => $value) {
            if (!is_string($key)) {
                return false;
            }
        }

        return true;
    }

    protected function valueToString(mixed $value): string
    {
        if (is_array($value) || is_object($value)) {
            return md5(serialize($this->value));
        } elseif (is_int($value) || is_float($value)) {
            return (string) $value;
        } elseif (is_bool($value)) {
            return $value ? 'true' : 'false';
        } elseif (is_null($value)) {
            return 'null';
        }

        return $value;
    }
}
