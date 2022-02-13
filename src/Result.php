<?php

namespace Crwlr\Crawler;

class Result
{
    /**
     * @var mixed[]
     */
    private array $data = [];

    public function __construct(private string $resourceName = 'Result')
    {
    }

    public function name(): string
    {
        return $this->resourceName;
    }

    public function set(string $key, mixed $value): void
    {
        if (array_key_exists($key, $this->data)) {
            if (is_array($this->data[$key])) {
                $this->data[$key][] = $value;
            } else {
                $this->data[$key] = [$this->data[$key], $value];
            }
        } else {
            $this->data[$key] = $value;
        }
    }

    public function get(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, $this->data)) {
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
}
