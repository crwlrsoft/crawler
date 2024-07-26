<?php

namespace Crwlr\Crawler\Steps\Refiners\String;

use Crwlr\Crawler\Steps\Refiners\AbstractRefiner;

class StrBeforeFirst extends AbstractRefiner
{
    public function __construct(protected readonly string $first) {}

    public function refine(mixed $value): mixed
    {
        if (!is_string($value)) {
            $this->logTypeWarning('StringRefiner::beforeFirst()', $value);

            return $value;
        }

        if ($this->first === '') {
            return '';
        }

        return trim(explode($this->first, $value)[0]);
    }
}
