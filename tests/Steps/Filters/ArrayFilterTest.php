<?php

namespace tests\Steps\Filters;

use Crwlr\Crawler\Steps\Filters\Filter;

it('filters an array of string values', function (array $values, bool $evaluationResult) {
    $filter = Filter::arrayHasElement()->where(Filter::equal('foo'));

    expect($filter->evaluate($values))->toBe($evaluationResult);
})->with([
    [['foo', 'bar', 'baz'], true],
    [['bar', 'baz', 'quz'], false],
]);

it('filters a multi-level array by a key of the array elements (which are also arrays)', function () {
    $values = [
        ['foo' => 'one', 'bar' => 'two'],
        ['foo' => 'two', 'bar' => 'three'],
        ['foo' => 'three', 'bar' => 'four'],
    ];

    $filter = Filter::arrayHasElement()->where('foo', Filter::equal('four'));

    expect($filter->evaluate($values))->toBeFalse();

    $filter = Filter::arrayHasElement()->where('foo', Filter::equal('two'));

    expect($filter->evaluate($values))->toBeTrue();
});

it('applies multiple complex filters on a multi-level array', function () {
    $values = [
        [
            'id' => '123',
            'name' => 'abc',
            'tags' => [
                ['type' => 'companyId', 'value' => '123'],
                ['type' => 'type', 'value' => 'job-ad'],
                ['type' => 'companyId', 'value' => '125'],
            ],
        ],
        [
            'id' => '124',
            'name' => 'abd',
            'tags' => [
                ['type' => 'companyId', 'value' => '123'],
                ['type' => 'type', 'value' => 'blog-post'],
                ['type' => 'author', 'value' => 'John Doe'],
            ],
        ],
        [
            'id' => '125',
            'name' => 'abf',
            'tags' => [
                ['type' => 'companyId', 'value' => '123'],
                ['type' => 'companyId', 'value' => '124'],
                ['type' => 'type', 'value' => 'job-ad'],
                ['type' => 'companyId', 'value' => '125'],
            ],
        ],
    ];

    $filter = Filter::arrayHasElement()
        ->where(
            'tags',
            Filter::arrayHasElement()
                ->where('type', Filter::equal('companyId'))
                ->where('value', Filter::equal('123')),
        )
        ->where(
            'tags',
            Filter::arrayHasElement()
                ->where('type', Filter::equal('companyId'))
                ->where('value', Filter::equal('124'))
                ->negate(),
        )
        ->where(
            'tags',
            Filter::arrayHasElement()
                ->where('type', Filter::equal('type'))
                ->where('value', Filter::equal('job-ad')),
        );

    expect($filter->evaluate($values))->toBeTrue();

    $filter = Filter::arrayHasElement()
        ->where(
            'tags',
            Filter::arrayHasElement()
                ->where('type', Filter::equal('companyId'))
                ->where('value', Filter::equal('123')),
        )
        ->where(
            'tags',
            Filter::arrayHasElement()
                ->where('type', Filter::equal('companyId'))
                ->where('value', Filter::equal('125'))
                ->negate(),
        )
        ->where(
            'tags',
            Filter::arrayHasElement()
                ->where('type', Filter::equal('type'))
                ->where('value', Filter::equal('job-ad')),
        );

    expect($filter->evaluate($values))->toBeFalse();
});
