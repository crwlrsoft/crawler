<?php

namespace Crwlr\Crawler\Steps\Filters;

use Crwlr\Crawler\Steps\Filters\Enums\UrlFilterRule;
use Exception;

class UrlFilter extends Filter
{
    public function __construct(private readonly UrlFilterRule $url, private readonly string $filterString)
    {
    }

    /**
     * @throws Exception
     */
    public function evaluate(mixed $valueInQuestion): bool
    {
        return $this->url->evaluate($this->getKey($valueInQuestion), $this->filterString);
    }
}
