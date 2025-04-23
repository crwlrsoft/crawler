<?php

namespace Crwlr\Crawler\Steps\Refiners\String;

class StrBeforeFirst extends AbstractStringRefiner
{
    public function __construct(protected readonly string $first) {}

    public function refine(mixed $value): mixed
    {
        return $this->apply($value, function ($value) {
            if ($this->first === '') {
                return '';
            }

            return trim(explode($this->first, $value)[0]);
        }, 'StringRefiner::beforeFirst()');
    }
}
