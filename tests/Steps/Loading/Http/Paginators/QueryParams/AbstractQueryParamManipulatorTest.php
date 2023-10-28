<?php

namespace tests\Steps\Loading\Http\Paginators\QueryParams;

use Crwlr\Crawler\Steps\Loading\Http\Paginators\QueryParams\AbstractQueryParamManipulator;
use Crwlr\QueryString\Query;

it('gets the current value of a query param', function () {
    $manipulator = new class ('foo') extends AbstractQueryParamManipulator {
        public string $currentParamValue = '';

        public function execute(Query $query): Query
        {
            $this->currentParamValue = $this->getCurrentValue($query);

            return $query;
        }
    };

    $manipulator->execute(Query::fromString('foo=bar'));

    expect($manipulator->currentParamValue)->toBe('bar');
});

it('gets the current value of a query param as integer', function () {
    $manipulator = new class ('foo') extends AbstractQueryParamManipulator {
        public int $currentParamValue = 0;

        public function execute(Query $query): Query
        {
            $this->currentParamValue = $this->getCurrentValueAsInt($query);

            return $query;
        }
    };

    $manipulator->execute(Query::fromString('foo=123'));

    expect($manipulator->currentParamValue)->toBe(123);
});
