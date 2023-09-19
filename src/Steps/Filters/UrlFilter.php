<?php

namespace Crwlr\Crawler\Steps\Filters;

use Crwlr\Crawler\Steps\Filters\Enums\UrlFilterRule;
use Exception;

class UrlFilter extends Filter
{
    public function __construct(protected readonly UrlFilterRule $filterRule, protected readonly string $filterString) {}

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
