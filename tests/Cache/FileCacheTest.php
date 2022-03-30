<?php

namespace tests\Cache;

use Crwlr\Crawler\Loader\Http\Messages\RespondedRequest;
use Crwlr\Crawler\Cache\FileCache;
use Crwlr\Crawler\Cache\HttpResponseCacheItem;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Psr\SimpleCache\InvalidArgumentException;

function helper_cachedir(): string
{
    return __DIR__ . '/cachedir';
}

/**
 * @param mixed[] $items
 * @throws InvalidArgumentException
 */
function helper_addMultipleItemsToCache(array $items, FileCache $cache): void
{
    foreach ($items as $item) {
        $cache->set($item->key(), $item);
    }
}

function helper_basicCacheItemWithRequestUrl(string $requestUrl): HttpResponseCacheItem
{
    return HttpResponseCacheItem::fromAggregate(
        new RespondedRequest(new Request('GET', $requestUrl), new Response())
    );
}

beforeEach(function () {
    if (!file_exists(helper_cachedir())) {
        mkdir(helper_cachedir());
    }
});

afterEach(function () {
    $files = scandir(helper_cachedir());

    if (is_array($files)) {
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            unlink(helper_cachedir() . '/' . $file);
        }
    }

    rmdir(helper_cachedir());
});

test('It caches HttpResponseCacheItems', function () {
    $aggregate = new RespondedRequest(new Request('GET', '/'), new Response());
    $cacheItem = HttpResponseCacheItem::fromAggregate($aggregate);
    $cache = new FileCache(helper_cachedir());

    expect($cache->set($cacheItem->key(), $cacheItem))->toBeTrue();
    expect(file_exists(helper_cachedir() . '/' . $cacheItem->key()))->toBeTrue();
    expect($cache->get($cacheItem->key()))->toBeInstanceOf(HttpResponseCacheItem::class);
});

test('It checks if it has a certain key', function () {
    $aggregate = new RespondedRequest(new Request('GET', '/'), new Response());
    $cacheItem = HttpResponseCacheItem::fromAggregate($aggregate);
    $cache = new FileCache(helper_cachedir());
    $cache->set($cacheItem->key(), $cacheItem);

    expect($cache->has($cacheItem->key()))->toBeTrue();
    expect($cache->has('otherKey'))->toBeFalse();
});

test('It can delete a cache item', function () {
    $aggregate = new RespondedRequest(new Request('GET', '/'), new Response());
    $cacheItem = HttpResponseCacheItem::fromAggregate($aggregate);
    $cache = new FileCache(helper_cachedir());
    $cache->set($cacheItem->key(), $cacheItem);
    expect($cache->has($cacheItem->key()))->toBeTrue();

    $cache->delete($cacheItem->key());

    expect($cache->has($cacheItem->key()))->toBeFalse();
});

test('It can clear the whole cache', function () {
    $cacheItem1 = helper_basicCacheItemWithRequestUrl('/foo');
    $cacheItem2 = helper_basicCacheItemWithRequestUrl('/bar');
    $cacheItem3 = helper_basicCacheItemWithRequestUrl('/baz');

    $cache = new FileCache(helper_cachedir());
    helper_addMultipleItemsToCache([$cacheItem1, $cacheItem2, $cacheItem3], $cache);

    expect($cache->has($cacheItem1->key()))->toBeTrue();
    expect($cache->has($cacheItem2->key()))->toBeTrue();
    expect($cache->has($cacheItem3->key()))->toBeTrue();

    $cache->clear();

    expect($cache->has($cacheItem1->key()))->toBeFalse();
    expect($cache->has($cacheItem2->key()))->toBeFalse();
    expect($cache->has($cacheItem3->key()))->toBeFalse();
});

test('It gets multiple items', function () {
    $cacheItem1 = helper_basicCacheItemWithRequestUrl('/foo');
    $cacheItem2 = helper_basicCacheItemWithRequestUrl('/bar');
    $cacheItem3 = helper_basicCacheItemWithRequestUrl('/baz');

    $cache = new FileCache(helper_cachedir());
    helper_addMultipleItemsToCache([$cacheItem1, $cacheItem2, $cacheItem3], $cache);

    $items = $cache->getMultiple([$cacheItem1->key(), $cacheItem2->key(), $cacheItem3->key()]);

    expect(reset($items)->request()->getUri()->__toString())->toBe('/foo');
    expect(next($items)->request()->getUri()->__toString())->toBe('/bar');
    expect(next($items)->request()->getUri()->__toString())->toBe('/baz');
});

test('It sets multiple items', function () {
    $cacheItem1 = helper_basicCacheItemWithRequestUrl('/foo');
    $cacheItem2 = helper_basicCacheItemWithRequestUrl('/bar');
    $cacheItem3 = helper_basicCacheItemWithRequestUrl('/baz');

    $cache = new FileCache(helper_cachedir());
    $cache->setMultiple([
        $cacheItem1->key() => $cacheItem1,
        $cacheItem2->key() => $cacheItem2,
        $cacheItem3->key() => $cacheItem3,
    ]);

    expect($cache->has($cacheItem1->key()))->toBeTrue();
    expect($cache->has($cacheItem2->key()))->toBeTrue();
    expect($cache->has($cacheItem3->key()))->toBeTrue();
});

test('It deletes multiple items', function () {
    $cacheItem1 = helper_basicCacheItemWithRequestUrl('/blog');
    $cacheItem2 = helper_basicCacheItemWithRequestUrl('/contact');
    $cacheItem3 = helper_basicCacheItemWithRequestUrl('/privacy');

    $cache = new FileCache(helper_cachedir());
    helper_addMultipleItemsToCache([$cacheItem1, $cacheItem2, $cacheItem3], $cache);

    $cache->deleteMultiple([$cacheItem1->key(), $cacheItem2->key(), $cacheItem3->key()]);

    expect($cache->has($cacheItem1->key()))->toBeFalse();
    expect($cache->has($cacheItem2->key()))->toBeFalse();
    expect($cache->has($cacheItem3->key()))->toBeFalse();
});
