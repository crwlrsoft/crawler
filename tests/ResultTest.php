<?php

namespace tests;

use Crwlr\Crawler\Result;

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

test('when you add something with empty string as key it creates a name with incrementing number', function () {
    $result = new Result();

    $result->set('', 'foo');

    expect($result->get('unnamed1'))->toBe('foo');

    $result->set('', 'bar');

    expect($result->get('unnamed2'))->toBe('bar');

    $result->set('', 'baz');

    expect($result->get('unnamed3'))->toBe('baz');
});

test('you can create a new instance from another instance', function () {
    $instance1 = new Result();

    $instance1->set('foo', 'bar');

    $instance2 = new Result($instance1);

    expect($instance1->get('foo'))->toBe('bar');

    expect($instance2->get('foo'))->toBe('bar');

    $instance2->set('baz', 'quz');

    expect($instance1->get('baz'))->toBeNull();

    expect($instance2->get('baz'))->toBe('quz');
});

test('it makes a proper array of arrays if you repeatedly add (associative) arrays with the same key', function () {
    $result = new Result();

    $result->set('foo', ['bar' => 'one', 'baz' => 'two']);

    expect($result->get('foo'))->toBe(['bar' => 'one', 'baz' => 'two']);

    $result->set('foo', ['bar' => 'three', 'baz' => 'four']);

    expect($result->get('foo'))->toBe([
        ['bar' => 'one', 'baz' => 'two'],
        ['bar' => 'three', 'baz' => 'four'],
    ]);
});
