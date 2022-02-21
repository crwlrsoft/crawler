<?php

namespace Crwlr\Crawler;

class Io
{
    private mixed $value;

    public function __construct(mixed $value, public ?Result $result = null)
    {
        $this->value = $value instanceof Io ? $value->get() : $value;

        if (!$this->result && $value instanceof Io) {
            $this->result = $value->result;
        }
    }

    public function get(): mixed
    {
        return $this->value;
    }
}
