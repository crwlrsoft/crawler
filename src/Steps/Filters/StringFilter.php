<?php

namespace Crwlr\Crawler\Steps\Filters;

use Crwlr\Crawler\Steps\Filters\Enums\StringFilterRule;
use Exception;

class StringFilter extends AbstractFilter
{
    public function __construct(
        protected readonly StringFilterRule $filterRule,
        protected readonly string $filterString,
    ) {}

    /**
     * @throws Exception
     */
    public function evaluate(mixed $valueInQuestion): bool
    {
        $valueInQuestion = $this->getKey($valueInQuestion);

        if (!is_string($valueInQuestion)) {
            return false;
        }

        return $this->filterRule->evaluate($valueInQuestion, $this->filterString);
    }
}
