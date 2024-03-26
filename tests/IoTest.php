<?php

namespace tests;

use Crwlr\Crawler\Io;
use Crwlr\Crawler\Result;

/**
 * @param mixed[] $keep
 */
function helper_getIoInstance(
    mixed $value,
    ?Result $result = null,
    ?Result $addLaterToResult = null,
    array $keep = [],
): Io {
    return new class ($value, $result, $addLaterToResult, $keep) extends Io {};
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

test('you can add an array with data that should be kept (see Step::keep() functionality)', function () {
    $keep = ['foo' => 'bar', 'baz' => 'quz'];

    $io = helper_getIoInstance('test', keep: $keep);

    expect($io->keep)->toBe($keep);
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

test('when created from another Io instance it passes on the data to keep', function () {
    $io1 = helper_getIoInstance('test', keep: ['co' => 'derotsch']);

    $io2 = helper_getIoInstance($io1);

    expect($io2->keep)->toBe(['co' => 'derotsch']);
});

test('the withValue() method creates a new instance with that value bot keeps the result and keep data', function () {
    $result = new Result();

    $result->set('foo', 'one');

    $addLaterResult = new Result();

    $result->set('bar', 'two');

    $io1 = helper_getIoInstance('hey', $result, $addLaterResult, ['baz' => 'three']);

    $io2 = $io1->withValue('ho');

    expect($io2->get())->toBe('ho')
        ->and($io2->result)->toBe($result)
        ->and($io2->addLaterToResult)->toBe($addLaterResult)
        ->and($io2->keep)->toBe(['baz' => 'three']);
});

test(
    'the withPropertyValue() method creates a new instance and replaces a certain property in its array value',
    function () {
        $result = new Result();

        $result->set('foo', 'one');

        $addLaterResult = new Result();

        $result->set('bar', 'two');

        $io1 = helper_getIoInstance(['a' => '1', 'b' => '2', 'c' => '3'], $result, $addLaterResult, ['baz' => 'three']);

        $io2 = $io1->withPropertyValue('c', '4');

        expect($io2->get())->toBe(['a' => '1', 'b' => '2', 'c' => '4'])
            ->and($io2->result)->toBe($result)
            ->and($io2->addLaterToResult)->toBe($addLaterResult)
            ->and($io2->keep)->toBe(['baz' => 'three']);
    }
);

test('if the property does not exist, it is added, when withPropertyValue() is used', function () {
    $io1 = helper_getIoInstance(['a' => '1', 'b' => '2']);

    $io2 = $io1->withPropertyValue('c', '3');

    expect($io2->get())->toBe(['a' => '1', 'b' => '2', 'c' => '3']);
});

it('gets a particular property by key from array output', function () {
    $io = helper_getIoInstance(['foo' => 'so', 'bar' => 'lala', 'baz' => 'bla']);

    expect($io->getProperty('bar'))->toBe('lala');
});

it('when the property does not exist, getProperty() returns the defined fallback value (default null)', function () {
    $io = helper_getIoInstance(['foo' => 'so', 'bar' => 'lala', 'baz' => 'bla']);

    expect($io->getProperty('quz'))->toBeNull()
        ->and($io->getProperty('quz', 123))->toBe(123);
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

it('adds data to keep when calling keep() and makes already existing keys an array', function () {
    $io = helper_getIoInstance('value', keep: ['foo' => 'one', 'bar' => 'two']);

    $io->keep(['bar' => 'three', 'baz' => 'four']);

    expect($io->keep)->toBe(['foo' => 'one', 'bar' => ['two', 'three'], 'baz' => 'four']);
});
