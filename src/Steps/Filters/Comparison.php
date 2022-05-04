<?php

namespace Crwlr\Crawler\Steps\Filters;

use Crwlr\Crawler\Steps\Filters\Enums\Comparisons;
use Exception;

class Comparison extends Filter
{
    public function __construct(private readonly Comparisons $comparison, private readonly mixed $compareTo)
    {
    }

    /**
     * @throws Exception
     */
    public function evaluate(mixed $valueInQuestion): bool
    {
        return $this->comparison->evaluate($this->getKey($valueInQuestion), $this->compareTo);
    }
}
