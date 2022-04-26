<?php

namespace Crwlr\Crawler\Steps;

use Crwlr\Crawler\Input;
use Crwlr\Crawler\Output;
use Crwlr\Crawler\Result;
use Exception;
use Generator;
use Psr\Log\LoggerInterface;

/**
 * Base class for classes Step and Group which share some things in terms of adding output data to Result objects.
 */

abstract class BaseStep implements StepInterface
{
    protected ?string $resultKey = null;

    /**
     * True means add all elements of the output array. Array of strings means, add just those keys.
     *
     * @var bool|string[]
     */
    protected bool|array $addToResult = false;

    protected ?LoggerInterface $logger = null;

    protected ?string $useInputKey = null;

    protected bool|string $uniqueOutput = false;

    /**
     * @var bool[]
     */
    protected array $uniqueOutputKeys = [];

    protected bool $cascades = true;

    /**
     * @param Input $input
     * @return Generator<Output>
     */
    abstract public function invokeStep(Input $input): Generator;

    public function addLogger(LoggerInterface $logger): static
    {
        $this->logger = $logger;

        return $this;
    }

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
    final public function getResultKey(): ?string
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

    final public function useInputKey(string $key): static
    {
        $this->useInputKey = $key;

        return $this;
    }

    final public function dontCascade(): static
    {
        $this->cascades = false;

        return $this;
    }

    final public function cascades(): bool
    {
        return $this->cascades;
    }

    final public function uniqueOutputs(?string $key = null): static
    {
        $this->uniqueOutput = $key ?? true;

        return $this;
    }

    public function resetAfterRun(): void
    {
        $this->uniqueOutputKeys = [];
    }

    /**
     * @throws Exception
     */
    final protected function getInputKeyToUse(Input $input): Input
    {
        if ($this->useInputKey !== null) {
            if (!array_key_exists($this->useInputKey, $input->get())) {
                throw new Exception('Key ' . $this->useInputKey . ' does not exist in input');
            }

            $input = new Input($input->get()[$this->useInputKey], $input->result);
        }

        return $input;
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
    private function addDataFromOutputArrayToResult(array $output, Result $result): void
    {
        foreach ($output as $key => $value) {
            if ($this->addToResult === true) {
                $result->set(is_string($key) ? $key : '', $value);
            } elseif (is_array($this->addToResult) && in_array($key, $this->addToResult, true)) {
                $result->set($this->choseResultKey($key), $value);
            }
        }
    }

    /**
     * When user defines an array of keys that shall be added to the result it can also contain a mapping.
     * If it does, use the key that it should be mapped to, instead of the key it has in the output array.
     */
    private function choseResultKey(int|string $keyInOutput): string
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