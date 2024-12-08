<?php

namespace Crwlr\Crawler\Steps\Filters;

use Exception;

class ArrayFilter extends AbstractFilter
{
    use Filterable;

    /**
     * @throws Exception
     */
    public function evaluate(mixed $valueInQuestion): bool
    {
        $valueInQuestion = $this->getKey($valueInQuestion);

        if (is_array($valueInQuestion) && !empty($valueInQuestion)) {
            foreach ($valueInQuestion as $value) {
                if ($this->passesAllFilters($value)) {
                    return true;
                }
            }
        }

        return false;
    }
}
