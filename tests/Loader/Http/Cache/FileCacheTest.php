<?php

namespace tests\Loader\Http\Cache;

use Crwlr\Crawler\Loader\Http\Cache\Exceptions\InvalidArgumentException;
use Crwlr\Crawler\Loader\Http\Cache\Exceptions\MissingZlibExtensionException;
use Crwlr\Crawler\Loader\Http\Messages\RespondedRequest;
use Crwlr\Crawler\Loader\Http\Cache\FileCache;
use Crwlr\Crawler\Loader\Http\Cache\HttpResponseCacheItem;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Utils;

function helper_cachedir(): string
{
    return __DIR__ . '/cachedir';
}

/**
 * @param mixed[] $items
 * @throws InvalidArgumentException
 * @throws MissingZlibExtensionException
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

it('compresses cache data when useCompression() is used', function () {
    $data = <<<DATA
        Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et
        dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet
        clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet. Lorem ipsum dolor sit amet,
        consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat,
        sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea
        takimata sanctus est Lorem ipsum dolor sit amet.
        DATA;

    $aggregate = new RespondedRequest(new Request('GET', '/compression'), new Response(body: Utils::streamFor($data)));

    $cacheItem = HttpResponseCacheItem::fromAggregate($aggregate);

    $cache = new FileCache(helper_cachedir());

    $cache->set($cacheItem->key(), $cacheItem);

    $uncompressedFileSize = filesize(helper_cachedir() . '/' . $cacheItem->key());

    clearstatcache(); // Results of filesize() are cached. Clear that to get correct result for compressed file size.

    $cache->useCompression();

    $cache->set($cacheItem->key(), $cacheItem);

    $compressedFileSize = filesize(helper_cachedir() . '/' . $cacheItem->key());

    expect($compressedFileSize)->toBeLessThan($uncompressedFileSize);

    // Didn't want to check for exact numbers, because I guess they could be a bit different on different systems.
    // But thought the diff should at least be more than 30% for the test to succeed.
    expect($uncompressedFileSize - $compressedFileSize)->toBeGreaterThan($uncompressedFileSize * 0.3);
});
