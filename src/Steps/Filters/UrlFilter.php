<?php

namespace Crwlr\Crawler\Steps\Filters;

use Crwlr\Crawler\Steps\Filters\Enums\UrlFilterRule;
use Exception;

class UrlFilter extends Filter
{
    public function __construct(protected readonly UrlFilterRule $filterRule, protected readonly string $filterString)
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
