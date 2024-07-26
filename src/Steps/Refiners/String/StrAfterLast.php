<?php

namespace Crwlr\Crawler\Steps\Refiners\String;

use Crwlr\Crawler\Steps\Refiners\AbstractRefiner;

class StrAfterLast extends AbstractRefiner
{
    public function __construct(protected readonly string $last) {}

    public function refine(mixed $value): mixed
    {
        if (!is_string($value)) {
            $this->logTypeWarning('StringRefiner::afterLast()', $value);

            return $value;
        }

        if ($this->last === '') {
            return '';
        }

        $split = explode($this->last, $value);

        $lastPart = end($split);

        return trim($lastPart);
    }
}
