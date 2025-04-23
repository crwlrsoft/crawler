<?php

namespace Crwlr\Crawler\Steps\Refiners\String;

class StrBeforeLast extends AbstractStringRefiner
{
    public function __construct(protected readonly string $last) {}

    public function refine(mixed $value): mixed
    {
        return $this->apply($value, function ($value) {
            if ($this->last === '') {
                return $value;
            }

            $split = explode($this->last, $value);

            if (count($split) === 1) {
                return $value;
            }

            array_pop($split);

            return trim(implode($this->last, $split));
        }, 'StringRefiner::beforeLast()');
    }
}
