<?php

namespace Crwlr\Crawler\Steps\Refiners\String;

use Crwlr\Crawler\Steps\Refiners\AbstractRefiner;

class StrBeforeLast extends AbstractRefiner
{
    public function __construct(protected readonly string $last) {}

    public function refine(mixed $value): mixed
    {
        if (!is_string($value)) {
            $this->logTypeWarning('Str::beforeLast()', $value);

            return $value;
        }

        if ($this->last === '') {
            return $value;
        }

        $split = explode($this->last, $value);

        if (count($split) === 1) {
            return $value;
        }

        array_pop($split);

        return trim(implode($this->last, $split));
    }
}
