<?php

namespace tests;

use Crwlr\Crawler\Result;

test('It can be created without a name', function () {
    expect(new Result())->toBeInstanceOf(Result::class);
});

test('You can set and get a property', function () {
    $result = new Result();
    $result->set('title', 'PHP Web Developer');
    expect($result->get('title'))->toBe('PHP Web Developer');
});

test('You can set multiple values for a property', function () {
    $result = new Result();
    $result->set('location', 'Linz');
    expect($result->get('location'))->toBe('Linz');
    $result->set('location', 'Wien');
    expect($result->get('location'))->toBe(['Linz', 'Wien']);
});

test('The get method has a default value that you can set yourself', function () {
    $result = new Result();
    expect($result->get('foo'))->toBeNull();
    expect($result->get('foo', '123'))->toBe('123');
});

test('You can convert it to a plain array', function () {
    $result = new Result();
    $result->set('title', 'PHP Web Developer (w/m/x)');
    $result->set('location', 'Linz');
    $result->set('location', 'Wien');

    expect($result->toArray())->toBe([
        'title' => 'PHP Web Developer (w/m/x)',
        'location' => ['Linz', 'Wien'],
    ]);
});
