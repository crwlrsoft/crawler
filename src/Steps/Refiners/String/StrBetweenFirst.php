<?php

namespace Crwlr\Crawler\Steps\Refiners\String;

class StrBetweenFirst extends AbstractStringRefiner
{
    public function __construct(protected readonly string $start, protected readonly string $end) {}

    public function refine(mixed $value): mixed
    {
        return $this->apply($value, function ($value) {
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
        }, 'StringRefiner::betweenFirst()');
    }
}
