<?php

use Crwlr\Crawler\Collection;

function testHelper_getCollection(array $array): Collection
{
    return new class($array) extends Collection
    {
    };
}

test('Can be created from an array.', function () {
    $collection = testHelper_getCollection([1, 2, 3]);
    expect($collection)->toBeInstanceOf(Collection::class);
});

test('It returns all items as array.', function () {
    $collection = testHelper_getCollection([1, 2, 3]);
    expect($collection->all())->toBe([1, 2, 3]);
});

test('You can iterate through it.', function () {
    $collection = testHelper_getCollection([1, 2, 3]);

    $numberOfItems = 0;

    foreach ($collection as $item) {
        $numberOfItems++;
    }

    expect($numberOfItems)->toBe(3);
});
