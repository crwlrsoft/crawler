<?php

namespace Crwlr\Crawler;

final class Result
{
    /**
     * @var mixed[]
     */
    private array $data = [];

    public function __construct(protected ?Result $result = null)
    {
        if ($result) {
            $this->data = $result->data;
        }
    }

    public function set(string $key, mixed $value): self
    {
        if ($key === '') {
            $key = $this->getUnnamedKey();
        }

        if (array_key_exists($key, $this->data)) {
            if (!is_array($this->data[$key]) || $this->isAssociativeArray($this->data[$key])) {
                $this->data[$key] = [$this->data[$key], $value];
            } else {
                $this->data[$key][] = $value;
            }
        } else {
            $this->data[$key] = $value;
        }

        return $this;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->data);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        if ($this->has($key)) {
            return $this->data[$key];
        }

        return $default;
    }

    /**
     * @return mixed[]
     */
    public function toArray(): array
    {
        return $this->data;
    }

    private function getUnnamedKey(): string
    {
        $i = 1;

        while ($this->get('unnamed' . $i) !== null) {
            $i++;
        }

        return 'unnamed' . $i;
    }

    /**
     * @param mixed[] $array
     */
    private function isAssociativeArray(array $array): bool
    {
        foreach ($array as $key => $value) {
            return is_string($key);
        }

        return false;
    }
}
