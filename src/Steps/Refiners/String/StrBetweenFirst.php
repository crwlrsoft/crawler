<?php

namespace Crwlr\Crawler\Steps\Refiners\String;

use Crwlr\Crawler\Steps\Refiners\AbstractRefiner;

class StrBetweenFirst extends AbstractRefiner
{
    public function __construct(protected readonly string $start, protected readonly string $end) {}

    public function refine(mixed $value): mixed
    {
        if (!is_string($value)) {
            $this->logTypeWarning('StringRefiner::betweenFirst()', $value);

            return $value;
        }

        if ($this->start === '') {
            $splitAtStart = ['', $value];
        } else {
            $splitAtStart = explode($this->start, $value, 2);
        }

        if (count($splitAtStart) === 2) {
            if ($this->end === '') {
                return trim($splitAtStart[1]);
            }

            return trim(explode($this->end, $splitAtStart[1])[0]);
        }

        return '';
    }
}
