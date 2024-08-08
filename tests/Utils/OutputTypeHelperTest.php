<?php

namespace tests\Utils;

use Crwlr\Crawler\Utils\OutputTypeHelper;
use stdClass;

it('converts an object with a toArrayForResult() method to an array', function () {
    $object = new class {
        /**
         * @return string[]
         */
        public function toArrayForResult(): array
        {
            return ['foo' => 'bar', 'baz'];
        }
    };

    expect(OutputTypeHelper::objectToArray($object))->toBe(['foo' => 'bar', 'baz']);
});

it('converts an object with a toArray() method to an array', function () {
    $object = new class {
        /**
         * @return string[]
         */
        public function toArray(): array
        {
            return ['foo' => 'bar'];
        }
    };

    expect(OutputTypeHelper::objectToArray($object))->toBe(['foo' => 'bar']);
});

it('converts an object with a __serialize() method to an array', function () {
    $object = new class {
        public function __serialize(): array
        {
            return ['winnie' => 'the pooh'];
        }
    };

    expect(OutputTypeHelper::objectToArray($object))->toBe(['winnie' => 'the pooh']);
});

it('converts an object to an array by just casting it', function () {
    $object = new class {
        public string $foo = 'one';

        public string $bar = 'two';
    };

    expect(OutputTypeHelper::objectToArray($object))->toBe(['foo' => 'one', 'bar' => 'two']);
});

it('checks if a value is a scalar value', function (mixed $value, bool $expectedResult) {
    expect(OutputTypeHelper::isScalar($value))->toBe($expectedResult);
})->with([
    ['foo', true],
    [123, true],
    [true, true],
    [false, true],
    [1.23, true],
    [['foo', 'bar'], true], // only associative array counts as non scalar for the output types
    [['foo' => 'bar'], false],
    [new stdClass(), false],
]);

it('checks if a value is an associative array', function (mixed $value, bool $expectedResult) {
    expect(OutputTypeHelper::isAssociativeArray($value))->toBe($expectedResult);
})->with([
    ['foo', false],
    [['foo', 'bar'], false],
    [['foo' => 'bar'], true],
    [new stdClass(), false],
]);

it(
    'checks if a value is an associative array or object (a.k.a. non-scalar)',
    function (mixed $value, bool $expectedResult) {
        expect(OutputTypeHelper::isAssociativeArrayOrObject($value))->toBe($expectedResult);
    },
)->with([
    ['foo', false],
    [['foo', 'bar'], false],
    [['foo' => 'bar'], true],
    [new stdClass(), true],
]);
