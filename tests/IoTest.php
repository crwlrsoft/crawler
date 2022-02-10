<?php

namespace tests;

use Crwlr\Crawler\Io;
use Crwlr\Crawler\Result;

function helper_getIoInstance(mixed $value, ?Result $result = null): Io
{
    return new class($value, $result) extends Io {
    };
}

test('It can be created with only a value.', function () {
    $io = helper_getIoInstance('test');
    expect($io)->toBeInstanceOf(Io::class);
});

test('You can add a Result object.', function () {
    $result = new Result();
    $io = helper_getIoInstance('test', $result);
    expect($io->result)->toBe($result);
});

test('You can create it from another Io instance and it keeps the value of the original instance.', function () {
    $io1 = helper_getIoInstance('test');
    $io2 = helper_getIoInstance($io1);
    expect($io2->get())->toBe('test');
});

test('When created from another Io instance it passes on the Result object.', function () {
    $result = new Result();
    $io1 = helper_getIoInstance('test', $result);
    $io2 = helper_getIoInstance($io1);
    expect($io2->result)->toBe($result);
});
