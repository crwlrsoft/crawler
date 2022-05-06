<?php

namespace Crwlr\Crawler\Steps\Filters;

use Crwlr\Crawler\Steps\Filters\Enums\ComparisonFilterRule;
use Exception;

class ComparisonFilter extends Filter
{
    public function __construct(private readonly ComparisonFilterRule $filterRule, private readonly mixed $compareTo)
    {
    }

    /**
     * @throws Exception
     */
    public function evaluate(mixed $valueInQuestion): bool
    {
        return $this->filterRule->evaluate($this->getKey($valueInQuestion), $this->compareTo);
    }
}
