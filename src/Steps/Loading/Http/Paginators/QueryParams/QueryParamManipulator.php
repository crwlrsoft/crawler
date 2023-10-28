<?php

namespace Crwlr\Crawler\Steps\Loading\Http\Paginators\QueryParams;

use Crwlr\QueryString\Query;

interface QueryParamManipulator
{
    public function execute(Query $query): Query;
}
