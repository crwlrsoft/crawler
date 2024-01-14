<?php

namespace Crwlr\Crawler\Steps\Loading\Http\Paginators\QueryParams;

use Adbar\Dot;
use Crwlr\QueryString\Query;
use Exception;

abstract class AbstractQueryParamManipulator implements QueryParamManipulator
{
    public function __construct(protected string $queryParamName) {}

    /**
     * @throws Exception
     */
    protected function getCurrentValue(Query $query, mixed $fallbackValue = null): mixed
    {
        if ($query->has($this->queryParamName)) {
            return $query->get($this->queryParamName);
        }

        return $fallbackValue;
    }

    /**
     * @throws Exception
     */
    protected function getCurrentValueUsingDotNotation(Query $query, mixed $fallbackValue = null): mixed
    {
        $dot = new Dot($query->toArray());

        return $dot->get($this->queryParamName, $fallbackValue);
    }

    /**
     * @throws Exception
     */
    protected function getCurrentValueAsInt(Query $query): int
    {
        return (int) $this->getCurrentValue($query);
    }

    /**
     * @throws Exception
     */
    protected function getCurrentValueAsIntUsingDotNotation(Query $query): int
    {
        return (int) $this->getCurrentValueUsingDotNotation($query);
    }
}
