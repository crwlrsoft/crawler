<?php

namespace Crwlr\Crawler;

class Io
{
    protected mixed $value;

    public function __construct(mixed $value, public ?Result $result = null)
    {
        if ($value instanceof self) {
            $this->value = $value->value;
            $this->result ??= $value->result;
            return;
        }

        $this->value = $value;
    }

    public function get(): mixed
    {
        return $this->value;
    }
}
