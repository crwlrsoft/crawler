<?php

namespace Crwlr\Crawler\Steps\Refiners\String;

use Crwlr\Crawler\Steps\Refiners\AbstractRefiner;

class StrReplace extends AbstractRefiner
{
    /**
     * @param string|string[] $search
     * @param string|string[] $replace
     */
    public function __construct(
        protected readonly string|array $search,
        protected readonly string|array $replace,
    ) {}

    public function refine(mixed $value): mixed
    {
        if (!is_string($value)) {
            $this->logTypeWarning('Str::replace()', $value);

            return $value;
        }

        $replaced = str_replace($this->search, $this->replace, $value);

        return trim($replaced);
    }
}
