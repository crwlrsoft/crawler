<?php

namespace Crwlr\Crawler\Steps\Filters;

use Crwlr\Crawler\Steps\Filters\Enums\ComparisonFilterRule;
use Exception;

class ComparisonFilter extends AbstractFilter
{
    public function __construct(
        protected readonly ComparisonFilterRule $filterRule,
        protected readonly mixed $compareTo,
    ) {}

    /**
     * @throws Exception
     */
    public function evaluate(mixed $valueInQuestion): bool
    {
        return $this->filterRule->evaluate($this->getKey($valueInQuestion), $this->compareTo);
    }
}
