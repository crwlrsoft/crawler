<?php

namespace tests\Steps\Loading\Http\Paginators\QueryParams;

use Crwlr\Crawler\Steps\Loading\Http\Paginators\QueryParams\Incrementor;
use Crwlr\QueryString\Query;

it('increments a query param value by a certain number', function () {
    $incrementor = new Incrementor('foo', 10);

    $query = Query::fromString('foo=-10');

    expect($query->get('foo'))->toBe('-10');

    $incrementor->execute($query);

    expect($query->get('foo'))->toBe('0');

    $incrementor->execute($query);

    expect($query->get('foo'))->toBe('10');

    $incrementor->execute($query);

    expect($query->get('foo'))->toBe('20');
});
