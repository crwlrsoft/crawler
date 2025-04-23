<?php

namespace Crwlr\Crawler\Steps\Refiners\String;

class StrBetweenLast extends AbstractStringRefiner
{
    public function __construct(protected readonly string $start, protected readonly string $end) {}

    public function refine(mixed $value): mixed
    {
        return $this->apply($value, function ($value) {
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
        }, 'StringRefiner::betweenLast()');
    }
}
