<?php

namespace Crwlr\Crawler\Steps;

use Crwlr\Crawler\Result;

/**
 * Base class for classes Step and Group which share some things in terms of adding output data to Result objects.
 */

abstract class AddsDataToResult
{
    protected ?string $resultKey = null;

    /**
     * True means add all elements of the output array. Array of strings means, add just those keys.
     *
     * @var bool|string[]
     */
    protected bool|array $addToResult = false;

    /**
     * When the output of a step is a simple value (not array), add it with this key to the Result.
     */
    public function setResultKey(string $key): static
    {
        $this->resultKey = $key;

        return $this;
    }

    /**
     * The key, the output value will be added to the result with (if set via setResultKey()).
     */
    public function getResultKey(): ?string
    {
        return $this->resultKey;
    }

    /**
     * When the output of a step is an array, call this method with null to add all it's elements/properties
     * to the Result, or provide an array with the keys that should be added.
     *
     * @param string[]|null $keys
     */
    public function addKeysToResult(?array $keys = null): static
    {
        $this->addToResult = $keys ?? true;

        return $this;
    }

    /**
     * @return bool
     */
    public function addsToOrCreatesResult(): bool
    {
        return $this->resultKey !== null || $this->addToResult !== false;
    }

    final protected function addOutputDataToResult(mixed $output, ?Result $result = null): ?Result
    {
        if ($this->addsToOrCreatesResult()) {
            if (!$result) {
                $result = new Result();
            }

            if ($this->resultKey !== null) {
                $result->set($this->resultKey, $output);
            }

            if ($this->addToResult !== false && is_array($output)) {
                $this->addDataFromOutputArrayToResult($output, $result);
            }
        }

        return $result;
    }

    /**
     * @param mixed[] $output
     */
    protected function addDataFromOutputArrayToResult(array $output, Result $result): void
    {
        foreach ($output as $key => $value) {
            if ($this->addToResult === true) {
                $result->set(is_string($key) ? $key : '', $value);
            } elseif (is_array($this->addToResult) && in_array($key, $this->addToResult, true)) {
                $result->set($this->choseResultKey($key), $value);
            }
        }
    }

    protected function choseResultKey(int|string $keyInOutput): string
    {
        if (is_array($this->addToResult)) {
            $mapToKey = array_search($keyInOutput, $this->addToResult, true);

            if (is_string($mapToKey)) {
                return $mapToKey;
            }
        }

        return is_string($keyInOutput) ? $keyInOutput : '';
    }
}
