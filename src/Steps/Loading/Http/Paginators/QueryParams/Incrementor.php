<?php

namespace Crwlr\Crawler\Steps\Loading\Http\Paginators\QueryParams;

use Crwlr\QueryString\Query;
use Exception;

class Incrementor extends AbstractQueryParamManipulator
{
    public function __construct(
        string $queryParamName,
        protected int $increment = 1
    ) {
        parent::__construct($queryParamName);
    }

    /**
     * @throws Exception
     */
    public function execute(Query $query): Query
    {
        return $query->set(
            $this->queryParamName,
            (string) ($this->getCurrentValueAsInt($query) + $this->increment),
        );
    }
}