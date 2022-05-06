<?php

namespace Crwlr\Crawler\Steps\Filters;

use Crwlr\Crawler\Steps\Filters\Enums\StringFilterRule;
use Exception;

class StringFilter extends Filter
{
    public function __construct(private readonly StringFilterRule $filterRule, private readonly string $filterString)
    {
    }

    /**
     * @throws Exception
     */
    public function evaluate(mixed $valueInQuestion): bool
    {
        return $this->filterRule->evaluate($this->getKey($valueInQuestion), $this->filterString);
    }
}
