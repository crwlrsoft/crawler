<?php

namespace Crwlr\Crawler\Steps\Filters;

use Crwlr\Crawler\Steps\Filters\Enums\StringChecks;
use Exception;

class StringCheck extends Filter
{
    public function __construct(private readonly StringChecks $stringCheck, private readonly string $filterString)
    {
    }

    /**
     * @throws Exception
     */
    public function evaluate(mixed $valueInQuestion): bool
    {
        return $this->stringCheck->evaluate($this->getKey($valueInQuestion), $this->filterString);
    }
}
