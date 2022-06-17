<?php

namespace Crwlr\Crawler;

class Io
{
    public function __construct(protected mixed $value, public ?Result $result = null)
    {
        if ($value instanceof self) {
            $this->value = $value->value;

            $this->result ??= $value->result;
        }
    }

    public function get(): mixed
    {
        return $this->value;
    }
}
