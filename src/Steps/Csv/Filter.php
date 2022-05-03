<?php

namespace Crwlr\Crawler\Steps\Csv;

use Crwlr\Crawler\Steps\FilterRules\Comparison;
use Crwlr\Crawler\Steps\FilterRules\StringCheck;

final class Filter
{
    public function __construct(
        private readonly string $columnName,
        private readonly Comparison|StringCheck $operator,
        private readonly mixed $filterValue,
    ) {
    }

    /**
     * @param mixed[] $csvRow
     * @return bool
     */
    public function matches(array $csvRow): bool
    {
        return isset($csvRow[$this->columnName]) &&
            $this->operator->evaluate($csvRow[$this->columnName], $this->filterValue);
    }
}
