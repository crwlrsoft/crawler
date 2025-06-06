<?php

namespace Crwlr\Crawler\Steps\Refiners\String;

class StrReplace extends AbstractStringRefiner
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
        return $this->apply($value, function ($value) {
            $replaced = str_replace($this->search, $this->replace, $value);

            return trim($replaced);
        }, 'StringRefiner::replace()');

        //        if (!is_string($value)) {
        //            $this->logTypeWarning('StringRefiner::replace()', $value);
        //
        //            return $value;
        //        }
        //
        //        $replaced = str_replace($this->search, $this->replace, $value);
        //
        //        return trim($replaced);
    }
}
