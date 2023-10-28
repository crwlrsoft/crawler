<?php

namespace tests\Steps\Loading\Http\Paginators\QueryParams;

use Crwlr\Crawler\Steps\Loading\Http\Paginators\QueryParams\Decrementor;
use Crwlr\QueryString\Query;

it('reduces a query param value by a certain number', function () {
    $decrementor = new Decrementor('foo', 10);

    $query = Query::fromString('foo=20');

    expect($query->get('foo'))->toBe('20');

    $decrementor->execute($query);

    expect($query->get('foo'))->toBe('10');

    $decrementor->execute($query);

    expect($query->get('foo'))->toBe('0');

    $decrementor->execute($query);

    expect($query->get('foo'))->toBe('-10');
});
