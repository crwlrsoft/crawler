<?php

namespace Crwlr\Crawler;

class Result
{
    /**
     * @var mixed[]
     */
    private array $data = [];

    public function set(string $key, mixed $value): void
    {
        if ($key === '') {
            $key = $this->getUnnamedKey();
        }

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

    private function getUnnamedKey(): string
    {
        $i = 1;

        while ($this->get('unnamed' . $i) !== null) {
            $i++;
        }

        return 'unnamed' . $i;
    }
}
