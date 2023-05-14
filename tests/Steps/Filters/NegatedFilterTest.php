<?php

namespace tests\Steps\Filters;

use Crwlr\Crawler\Steps\Filters\Filter;
use Crwlr\Crawler\Steps\Filters\NegatedFilter;

it('wraps another filter and negates it', function () {
    $filter = Filter::equal('foo');

    $negatedFilter = new NegatedFilter($filter);

    expect($filter->evaluate('foo'))->toBeTrue();

    expect($negatedFilter->evaluate('foo'))->toBeFalse();

    expect($filter->evaluate('bar'))->toBeFalse();

    expect($negatedFilter->evaluate('bar'))->toBeTrue();
});
