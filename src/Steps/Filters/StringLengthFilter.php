<?php

namespace Crwlr\Crawler\Steps\Filters;

use Crwlr\Crawler\Steps\Filters\Enums\StringLengthFilterRule;
use Exception;

class StringLengthFilter extends Filter
{
    public function __construct(
        protected readonly StringLengthFilterRule $filterRule,
        protected readonly int $compareToLength,
    ) {
    }

    /**
     * @throws Exception
     */
    public function evaluate(mixed $valueInQuestion): bool
    {
        return $this->filterRule->evaluate($this->getKey($valueInQuestion), $this->compareToLength);
    }
}
