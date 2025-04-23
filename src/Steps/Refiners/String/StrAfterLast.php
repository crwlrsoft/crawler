<?php

namespace Crwlr\Crawler\Steps\Refiners\String;

class StrAfterLast extends AbstractStringRefiner
{
    public function __construct(protected readonly string $last) {}

    public function refine(mixed $value): mixed
    {
        return $this->apply($value, function ($value) {
            if ($this->last === '') {
                return '';
            }

            $split = explode($this->last, $value);

            $lastPart = end($split);

            return trim($lastPart);
        }, 'StringRefiner::afterLast()');
    }
}
