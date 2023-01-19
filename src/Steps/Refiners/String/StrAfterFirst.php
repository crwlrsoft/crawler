<?php

namespace Crwlr\Crawler\Steps\Refiners\String;

use Crwlr\Crawler\Steps\Refiners\AbstractRefiner;

class StrAfterFirst extends AbstractRefiner
{
    public function __construct(protected readonly string $first)
    {
    }

    public function refine(mixed $value): mixed
    {
        if (!is_string($value)) {
            $this->logTypeWarning('Str::afterFirst()', $value);

            return $value;
        }

        if ($this->first === '') {
            return $value;
        }

        $split = explode($this->first, $value, 2);

        $lastPart = end($split);

        return trim($lastPart);
    }
}
