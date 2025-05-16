<?php

namespace tests;

use Crwlr\Crawler\Loader\Http\Browser\Screenshot;
use Crwlr\Crawler\Loader\Http\Messages\RespondedRequest;
use Crwlr\Crawler\Result;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use stdClass;

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

    expect($result->get('foo'))->toBeNull()
        ->and($result->get('foo', '123'))->toBe('123');
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

test('Converting to an array, also converts all objects at any level in the array to arrays', function () {
    $result = new Result();

    $result->set('foo', 'one');

    $result->set(
        'bar',
        helper_getStdClassWithData([
            'a' => 'b',
            'c' => helper_getStdClassWithData(['d' => 'e', 'f' => 'g'])
        ]),
    );

    $resultArray = $result->toArray();

    expect($resultArray)->toBe([
        'foo' => 'one',
        'bar' => [
            'a' => 'b',
            'c' => ['d' => 'e', 'f' => 'g'],
        ],
    ]);
});

test(
    'when the only element of the output array is some unnamed property, but the value is an array with keys, ' .
    'it returns only that child array',
    function () {
        $result = new Result();

        $result->set('unnamed', new RespondedRequest(
            new Request('GET', 'https://www.example.com/foo'),
            new Response(200, [], 'Hello World!'),
            [new Screenshot('/path/to/screenshot.png')],
        ));

        $resultArray = $result->toArray();

        expect($resultArray)->toBeArray()
            ->and(count($resultArray))->toBeGreaterThanOrEqual(14)
            ->and($resultArray['url'])->toBe('https://www.example.com/foo')
            ->and($resultArray['status'])->toBe(200)
            ->and($resultArray['body'])->toBe('Hello World!')
            ->and($resultArray['screenshots'][0])->toBe('/path/to/screenshot.png');
    }
);

test(
    'when the only element of the output array is an unnamed property, with a scalar value, it returns the unnamed key',
    function () {
        $result = new Result();

        $result->set('unnamed', 'foo');

        $resultArray = $result->toArray();

        expect($resultArray)->toBe(['unnamed' => 'foo']);
    }
);

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

    expect($instance1->get('foo'))->toBe('bar')
        ->and($instance2->get('foo'))->toBe('bar');

    $instance2->set('baz', 'quz');

    expect($instance1->get('baz'))->toBeNull()
        ->and($instance2->get('baz'))->toBe('quz');
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
