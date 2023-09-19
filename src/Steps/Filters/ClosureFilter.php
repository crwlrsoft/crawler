<?php

namespace Crwlr\Crawler\Steps\Filters;

use Closure;
use Exception;

class ClosureFilter extends Filter
{
    public function __construct(
        protected readonly Closure $closure,
    ) {}

    /**
     * @throws Exception
     */
    public function evaluate(mixed $valueInQuestion): bool
    {
        $valueInQuestion = $this->getKey($valueInQuestion);

        return $this->closure->call($this, $valueInQuestion);
    }
}
