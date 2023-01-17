<?php

namespace tests\Cache;

use Crwlr\Crawler\Cache\CacheItem;
use DateInterval;
use DateTimeImmutable;

it('is serializable and unserializable without loss', function () {
    $createdAt = new DateTimeImmutable('2023-01-10 12:10:00');

    $item = new CacheItem('value', 'key123', 123, $createdAt);

    $serialized = serialize($item);

    $unserialized = unserialize($serialized);

    expect($unserialized->value())->toBe('value');

    expect($unserialized->key())->toBe('key123');

    expect($unserialized->ttl)->toBe(123);

    expect($unserialized->createdAt->format('Y-m-d H:i:s'))->toBe('2023-01-10 12:10:00');
});

it('creates a key based on the value if you don\'t provide a key manually', function () {
    $item = new CacheItem('foo');

    expect($item->key())->toBeString();

    expect(strlen($item->key()))->toBeGreaterThan(0);
});

it('tells if it is expired already', function () {
    $item = new CacheItem('v', 'k', 10);

    expect($item->isExpired())->toBeFalse();

    $item = new CacheItem('v', 'k', 10, (new DateTimeImmutable())->sub(new DateInterval('PT9S')));

    expect($item->isExpired())->toBeFalse();

    $item = new CacheItem('v', 'k', 10, (new DateTimeImmutable())->sub(new DateInterval('PT11S')));

    expect($item->isExpired())->toBeTrue();
});
