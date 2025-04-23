<?php

namespace Crwlr\Crawler\Steps\Refiners\String;

class StrAfterFirst extends AbstractStringRefiner
{
    public function __construct(protected readonly string $first) {}

    public function refine(mixed $value): mixed
    {
        return $this->apply($value, function ($value) {
            if ($this->first === '') {
                return $value;
            }

            $split = explode($this->first, $value, 2);

            $lastPart = end($split);

            return trim($lastPart);
        }, 'StringRefiner::afterFirst()');
    }
}
