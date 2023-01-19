<?php

namespace Crwlr\Crawler\Steps\Filters;

use Crwlr\Crawler\Steps\Filters\Enums\StringFilterRule;
use Exception;

class StringFilter extends Filter
{
    public function __construct(
        protected readonly StringFilterRule $filterRule,
        protected readonly string $filterString,
    ) {
    }

    /**
     * @throws Exception
     */
    public function evaluate(mixed $valueInQuestion): bool
    {
        return $this->filterRule->evaluate($this->getKey($valueInQuestion), $this->filterString);
    }
}
