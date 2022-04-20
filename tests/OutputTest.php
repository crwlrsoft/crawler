<?php

namespace tests;

use Crwlr\Crawler\Output;
use stdClass;

/**
 * @param mixed[] $data
 */
function helper_getStdClassWithData(array $data): stdClass
{
    $object = new stdClass();

    foreach ($data as $key => $value) {
        $object->{$key} = $value;
    }

    return $object;
}

it('sets a simple value key', function ($value, $key) {
    $output = new Output($value);

    expect($output->setKey())->toBe($key);

    expect($output->getKey())->toBe($key);
})->with([
    ['foo', 'foo'],
    [123, '123'],
    [123.1234, '123.1234'],
    [true, 'true'],
    [false, 'false'],
    [null, 'null'],
]);

it('sets a key from array output', function () {
    $output = new Output(['foo' => 'bar', 'yo' => 123.45]);

    expect($output->setKey('yo'))->toBe('123.45');

    expect($output->getKey())->toBe('123.45');
});

it('sets a key from object output', function () {
    $value = helper_getStdClassWithData(['foo' => 'bar', 'yo' => 123.45]);

    $output = new Output($value);

    expect($output->setKey('yo'))->toBe('123.45');

    expect($output->getKey())->toBe('123.45');
});

it('creates a string key for array output when not providing a key name', function () {
    $output = new Output(['one', 'two', 'three']);

    expect($output->setKey())->toBe('6975f1fd65cae4b21e32f4f47bf153a8');

    expect($output->getKey())->toBe('6975f1fd65cae4b21e32f4f47bf153a8');
});

it('creates a string key for object output when not providing a key name', function () {
    $object = helper_getStdClassWithData(['one', 'two', 'three']);

    $output = new Output($object);

    expect($output->setKey())->toBe('bb8dd69ea029ca1379df3994721f5fa9');

    expect($output->getKey())->toBe('bb8dd69ea029ca1379df3994721f5fa9');
});

it('creates a string key for array output when provided key name doesn\'t exist in output array', function () {
    $output = new Output(['one', 'two', 'three']);

    expect($output->setKey('four'))->toBe('6975f1fd65cae4b21e32f4f47bf153a8');

    expect($output->getKey())->toBe('6975f1fd65cae4b21e32f4f47bf153a8');
});

it('creates a string key for array output when provided key name doesn\'t exist in output object', function () {
    $object = helper_getstdClassWithData(['one', 'two', 'three']);

    $output = new Output($object);

    expect($output->setKey('four'))->toBe('bb8dd69ea029ca1379df3994721f5fa9');

    expect($output->getKey())->toBe('bb8dd69ea029ca1379df3994721f5fa9');
});

test('getKey returns a key when setKey was not called yet', function () {
    $output = new Output('test');

    expect($output->getKey())->toBe('test');
});
