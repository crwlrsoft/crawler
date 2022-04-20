<?php

namespace Crwlr\Crawler;

class Output extends Io
{
    private string|int|float|bool|null $key = null;

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

    private function valueToString(mixed $value): string
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
