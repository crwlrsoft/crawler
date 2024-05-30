<?php

namespace Crwlr\Crawler\Steps\Loading\Http\Paginators\QueryParams;

use Adbar\Dot;
use Crwlr\QueryString\Query;
use Exception;

class Incrementor extends AbstractQueryParamManipulator
{
    public function __construct(
        string $queryParamName,
        protected int $increment = 1,
        protected bool $useDotNotation = false,
    ) {
        parent::__construct($queryParamName);
    }

    /**
     * @throws Exception
     */
    public function execute(Query $query): Query
    {
        if ($this->useDotNotation) {
            $dot = (new Dot($query->toArray()))->set(
                $this->queryParamName,
                (string) ($this->getCurrentValueAsIntUsingDotNotation($query) + $this->increment),
            );

            return new Query($dot->all());
        }

        return $query->set(
            $this->queryParamName,
            (string) ($this->getCurrentValueAsInt($query) + $this->increment),
        );
    }
}
