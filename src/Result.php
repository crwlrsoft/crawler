<?php

namespace Crwlr\Crawler;

class Result
{
    private array $data = [];

    public function __construct(private string $resourceName = 'Result')
    {
    }

    public function setProperty(string $key, mixed $value)
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

    public function toArray(): array
    {
        return $this->data;
    }
}
