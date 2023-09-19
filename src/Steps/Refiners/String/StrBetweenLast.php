<?php

namespace Crwlr\Crawler\Steps\Refiners\String;

use Crwlr\Crawler\Steps\Refiners\AbstractRefiner;

class StrBetweenLast extends AbstractRefiner
{
    public function __construct(protected readonly string $start, protected readonly string $end) {}

    public function refine(mixed $value): mixed
    {
        if (!is_string($value)) {
            $this->logTypeWarning('Str::betweenLast()', $value);

            return $value;
        }

        if ($this->start === '') {
            $splitAtStart = ['', $value];
        } else {
            $splitAtStart = explode($this->start, $value);
        }

        $lastPart = end($splitAtStart);

        if ($this->end === '') {
            return trim($lastPart);
        }

        return trim(explode($this->end, $lastPart)[0]);
    }
}
