<?php

namespace tests;

use Crwlr\Crawler\Io;
use Crwlr\Crawler\Result;

function helper_getIoInstance(mixed $value, ?Result $result = null, ?Result $addLaterToResult = null): Io
{
    return new class ($value, $result, $addLaterToResult) extends Io {};
}

it('can be created with only a value.', function () {
    $io = helper_getIoInstance('test');

    expect($io)->toBeInstanceOf(Io::class);
});

test('you can add a Result object.', function () {
    $result = new Result();

    $io = helper_getIoInstance('test', $result);

    expect($io->result)->toBe($result);
});

test('you can add a secondary Result object that should be added to the main Result object later.', function () {
    $addLaterToResult = new Result();

    $io = helper_getIoInstance('test', addLaterToResult: $addLaterToResult);

    expect($io->addLaterToResult)->toBe($addLaterToResult);
});

test('you can create it from another Io instance and it keeps the value of the original instance.', function () {
    $io1 = helper_getIoInstance('test');

    $io2 = helper_getIoInstance($io1);

    expect($io2->get())->toBe('test');
});

test('when created from another Io instance it passes on the Result object.', function () {
    $result = new Result();

    $io1 = helper_getIoInstance('test', $result);

    $io2 = helper_getIoInstance($io1);

    expect($io2->result)->toBe($result);
});

test('when created from another Io instance it passes on the secondary Result object.', function () {
    $addLaterToResult = new Result();

    $io1 = helper_getIoInstance('test', addLaterToResult: $addLaterToResult);

    $io2 = helper_getIoInstance($io1);

    expect($io2->addLaterToResult)->toBe($addLaterToResult);
});

it('sets a simple value key', function ($value, $key) {
    $io = helper_getIoInstance($value);

    expect($io->setKey())->toBe($key);

    expect($io->getKey())->toBe($key);
})->with([
    ['foo', 'foo'],
    [123, '123'],
    [123.1234, '123.1234'],
    [true, 'true'],
    [false, 'false'],
    [null, 'null'],
]);

it('sets a key from array output', function () {
    $io = helper_getIoInstance(['foo' => 'bar', 'yo' => 123.45]);

    expect($io->setKey('yo'))->toBe('123.45');

    expect($io->getKey())->toBe('123.45');
});

it('sets a key from object output', function () {
    $value = helper_getStdClassWithData(['foo' => 'bar', 'yo' => 123.45]);

    $io = helper_getIoInstance($value);

    expect($io->setKey('yo'))->toBe('123.45');

    expect($io->getKey())->toBe('123.45');
});

it('creates a string key for array output when not providing a key name', function () {
    $io = helper_getIoInstance(['one', 'two', 'three']);

    expect($io->setKey())->toBe('6975f1fd65cae4b21e32f4f47bf153a8');

    expect($io->getKey())->toBe('6975f1fd65cae4b21e32f4f47bf153a8');
});

it('creates a string key for object output when not providing a key name', function () {
    $object = helper_getStdClassWithData(['one', 'two', 'three']);

    $io = helper_getIoInstance($object);

    expect($io->setKey())->toBe('bb8dd69ea029ca1379df3994721f5fa9');

    expect($io->getKey())->toBe('bb8dd69ea029ca1379df3994721f5fa9');
});

it('creates a string key for array output when provided key name doesn\'t exist in output array', function () {
    $io = helper_getIoInstance(['one', 'two', 'three']);

    expect($io->setKey('four'))->toBe('6975f1fd65cae4b21e32f4f47bf153a8');

    expect($io->getKey())->toBe('6975f1fd65cae4b21e32f4f47bf153a8');
});

it('creates a string key for array output when provided key name doesn\'t exist in output object', function () {
    $object = helper_getstdClassWithData(['one', 'two', 'three']);

    $io = helper_getIoInstance($object);

    expect($io->setKey('four'))->toBe('bb8dd69ea029ca1379df3994721f5fa9');

    expect($io->getKey())->toBe('bb8dd69ea029ca1379df3994721f5fa9');
});

test('getKey returns a key when setKey was not called yet', function () {
    $io = helper_getIoInstance('test');

    expect($io->getKey())->toBe('test');
});

test('isArrayWithStringKeys returns true when the value is an array with string keys', function () {
    $io = helper_getIoInstance(['foo' => 'one', 'bar' => 'two', 'baz' => 'three']);

    expect($io->isArrayWithStringKeys())->toBeTrue();
});

test('isArrayWithStringKeys returns false when the value is not an array with string keys', function ($value) {
    $io = helper_getIoInstance($value);

    expect($io->isArrayWithStringKeys())->toBeFalse();
})->with([
    123,
    true,
    ['foo', 'bar'],
    helper_getStdClassWithData(['foo' => 'bar']),
]);
