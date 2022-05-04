<?php

namespace tests\Steps\Filters;

use Crwlr\Crawler\Steps\Filters\Filter;
use Exception;
use InvalidArgumentException;

use function tests\helper_getStdClassWithData;

class TestFilter extends Filter
{
    public string $value = '';

    public function evaluate(mixed $valueInQuestion): bool
    {
        $this->value = $this->getKey($valueInQuestion);

        return true;
    }
}

it('gets a key from an array', function () {
    $filter = new TestFilter();

    $filter->useKey('foo');

    $filter->evaluate(['foo' => 'fooValue', 'bar' => 'barValue']);

    expect($filter->value)->toBe('fooValue');
});

it('gets a key from an object', function () {
    $filter = new TestFilter();

    $filter->useKey('foo');

    $filter->evaluate(helper_getStdClassWithData(['foo' => 'fooValue', 'bar' => 'barValue']));

    expect($filter->value)->toBe('fooValue');
});

it('throws an exception when the value in question is not array or object when a key to use was defined', function () {
    $filter = new TestFilter();

    $filter->useKey('foo');

    $filter->evaluate('foo');
})->throws(InvalidArgumentException::class);

it('throws an exception when the key to use is not contained in an array', function () {
    $filter = new TestFilter();

    $filter->useKey('foo');

    $filter->evaluate(['bar' => 'barValue', 'baz' => 'bazValue']);
})->throws(Exception::class);

it('throws an exception when the key to use is not contained in an object', function () {
    $filter = new TestFilter();

    $filter->useKey('foo');

    $filter->evaluate(helper_getStdClassWithData(['bar' => 'barValue', 'baz' => 'bazValue']));
})->throws(Exception::class);
