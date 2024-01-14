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

it('reduces a non first level query param value by a certain number', function () {
    $decrementor = new Decrementor('foo.bar.baz', 7, true);

    $query = Query::fromString('foo[bar][baz]=10');

    expect($decrementor->execute($query)->toString())->toBe('foo%5Bbar%5D%5Bbaz%5D=3');
});
