<?php

namespace tests\Cache;

use Crwlr\Crawler\Cache\CacheItem;
use Crwlr\Crawler\Cache\Exceptions\MissingZlibExtensionException;
use Crwlr\Crawler\Loader\Http\Messages\RespondedRequest;
use Crwlr\Crawler\Cache\FileCache;
use Crwlr\Crawler\Steps\Loading\Http;
use DateInterval;
use DateTimeImmutable;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Utils;
use PHPUnit\Framework\TestCase;

use function tests\helper_cachedir;
use function tests\helper_resetCacheDir;

/**
 * @param mixed[] $items
 * @throws MissingZlibExtensionException
 */
function helper_addMultipleItemsToCache(array $items, FileCache $cache): void
{
    foreach ($items as $item) {
        $cache->set($item->cacheKey(), $item);
    }
}

function helper_respondedRequestWithRequestUrl(string $requestUrl): RespondedRequest
{
    return new RespondedRequest(new Request('GET', $requestUrl), new Response());
}

/**
 * Helper function to get the CacheItem instance, because FileCache::get() returns only
 * the value wrapped in the CacheItem object.
 */
function helper_getCacheItemByKey(string $key): ?CacheItem
{
    $cacheFileContent = file_get_contents(helper_cachedir() . '/' . $key);

    $cacheItem = unserialize($cacheFileContent !== false ? $cacheFileContent : 'a:0:{}');

    return $cacheItem instanceof CacheItem ? $cacheItem : null;
}

afterEach(function () {
    helper_resetCacheDir();
});

/** @var TestCase $this */

it('caches a simple value', function () {
    $cache = new FileCache(helper_cachedir());

    $cache->set('user', 'otsch');

    expect($cache->get('user'))->toBe('otsch');
});

it('caches RespondedRequest objects', function () {
    $respondedRequest = new RespondedRequest(new Request('GET', '/'), new Response());

    $cache = new FileCache(helper_cachedir());

    expect($cache->set($respondedRequest->cacheKey(), $respondedRequest))->toBeTrue()
        ->and(file_exists(helper_cachedir() . '/' . $respondedRequest->cacheKey()))->toBeTrue()
        ->and($cache->get($respondedRequest->cacheKey()))->toBeInstanceOf(RespondedRequest::class);
});

it('checks if it has an item for a certain key', function () {
    $respondedRequest = new RespondedRequest(new Request('GET', '/'), new Response());

    $cache = new FileCache(helper_cachedir());

    $cache->set($respondedRequest->cacheKey(), $respondedRequest);

    expect($cache->has($respondedRequest->cacheKey()))->toBeTrue()
        ->and($cache->has('otherKey'))->toBeFalse();
});

it('does not return expired items', function () {
    $respondedRequest = new RespondedRequest(new Request('GET', '/'), new Response());

    $cacheItem = new CacheItem(
        $respondedRequest,
        $respondedRequest->cacheKey(),
        10,
        (new DateTimeImmutable())->sub(new DateInterval('PT11S')),
    );

    $cache = new FileCache(helper_cachedir());

    $cache->set($cacheItem->key(), $cacheItem);

    expect($cache->has($cacheItem->key()))->toBeFalse()
        ->and($cache->get($cacheItem->key()))->toBeNull();
});

it('deletes a cache item', function () {
    $respondedRequest = new RespondedRequest(new Request('GET', '/'), new Response());

    $cache = new FileCache(helper_cachedir());

    $cache->set($respondedRequest->cacheKey(), $respondedRequest);

    expect($cache->has($respondedRequest->cacheKey()))->toBeTrue();

    $cache->delete($respondedRequest->cacheKey());

    expect($cache->has($respondedRequest->cacheKey()))->toBeFalse();
});

it('deletes an expired cache item when has() is called with its key', function () {
    $cacheItem = new CacheItem('bar', 'foo', 10, (new DateTimeImmutable())->sub(new DateInterval('PT11S')));

    $cache = new FileCache(helper_cachedir());

    $cache->set('foo', $cacheItem);

    expect(file_exists(helper_cachedir() . '/foo'))->toBeTrue()
        ->and($cache->has('foo'))->toBeFalse()
        ->and(file_exists(helper_cachedir() . '/foo'))->toBeFalse();
});

it('deletes an expired cache item when get() is called with its key', function () {
    $cacheItem = new CacheItem('bar', 'foo', 10, (new DateTimeImmutable())->sub(new DateInterval('PT11S')));

    $cache = new FileCache(helper_cachedir());

    $cache->set('foo', $cacheItem);

    expect(file_exists(helper_cachedir() . '/foo'))->toBeTrue()
        ->and($cache->get('foo', 'defaultValue'))->toBe('defaultValue')
        ->and(file_exists(helper_cachedir() . '/foo'))->toBeFalse();
});

it('clears the whole cache', function () {
    $cacheItem1 = helper_respondedRequestWithRequestUrl('/foo');

    $cacheItem2 = helper_respondedRequestWithRequestUrl('/bar');

    $cacheItem3 = helper_respondedRequestWithRequestUrl('/baz');

    $cache = new FileCache(helper_cachedir());

    helper_addMultipleItemsToCache([$cacheItem1, $cacheItem2, $cacheItem3], $cache);

    expect($cache->has($cacheItem1->cacheKey()))->toBeTrue()
        ->and($cache->has($cacheItem2->cacheKey()))->toBeTrue()
        ->and($cache->has($cacheItem3->cacheKey()))->toBeTrue();

    $cache->clear();

    expect($cache->has($cacheItem1->cacheKey()))->toBeFalse()
        ->and($cache->has($cacheItem2->cacheKey()))->toBeFalse()
        ->and($cache->has($cacheItem3->cacheKey()))->toBeFalse();
});

it('gets multiple items', function () {
    $cacheItem1 = helper_respondedRequestWithRequestUrl('/foo');

    $cacheItem2 = helper_respondedRequestWithRequestUrl('/bar');

    $cacheItem3 = helper_respondedRequestWithRequestUrl('/baz');

    $cache = new FileCache(helper_cachedir());

    helper_addMultipleItemsToCache([$cacheItem1, $cacheItem2, $cacheItem3], $cache);

    $items = $cache->getMultiple([$cacheItem1->cacheKey(), $cacheItem2->cacheKey(), $cacheItem3->cacheKey()]);

    expect(reset($items)->request->getUri()->__toString())->toBe('/foo')
        ->and(next($items)->request->getUri()->__toString())->toBe('/bar')
        ->and(next($items)->request->getUri()->__toString())->toBe('/baz');
});

it('sets multiple items', function () {
    $cacheItem1 = helper_respondedRequestWithRequestUrl('/foo');

    $cacheItem2 = helper_respondedRequestWithRequestUrl('/bar');

    $cacheItem3 = helper_respondedRequestWithRequestUrl('/baz');

    $cache = new FileCache(helper_cachedir());

    $cache->setMultiple([
        $cacheItem1->cacheKey() => $cacheItem1,
        $cacheItem2->cacheKey() => $cacheItem2,
        $cacheItem3->cacheKey() => $cacheItem3,
    ]);

    expect($cache->has($cacheItem1->cacheKey()))->toBeTrue()
        ->and($cache->has($cacheItem2->cacheKey()))->toBeTrue()
        ->and($cache->has($cacheItem3->cacheKey()))->toBeTrue();
});

it('deletes multiple items', function () {
    $cacheItem1 = helper_respondedRequestWithRequestUrl('/blog');

    $cacheItem2 = helper_respondedRequestWithRequestUrl('/contact');

    $cacheItem3 = helper_respondedRequestWithRequestUrl('/privacy');

    $cache = new FileCache(helper_cachedir());

    helper_addMultipleItemsToCache([$cacheItem1, $cacheItem2, $cacheItem3], $cache);

    $cache->deleteMultiple([$cacheItem1->cacheKey(), $cacheItem2->cacheKey(), $cacheItem3->cacheKey()]);

    expect($cache->has($cacheItem1->cacheKey()))->toBeFalse()
        ->and($cache->has($cacheItem2->cacheKey()))->toBeFalse()
        ->and($cache->has($cacheItem3->cacheKey()))->toBeFalse();
});

it('can still use legacy (pre CacheItem object) cache files', function () {
    $content = file_get_contents(__DIR__ . '/_cachefilecontent');

    file_put_contents(helper_cachedir() . '/foo', $content);

    $cache = new FileCache(helper_cachedir());

    expect($cache->has('foo'))->toBeTrue();

    $cacheItem = $cache->get('foo');

    expect($cacheItem)->toBeArray();

    $respondedRequest = RespondedRequest::fromArray($cacheItem);

    expect($respondedRequest)->toBeInstanceOf(RespondedRequest::class)
        ->and($respondedRequest->requestedUri())->toBe(
            'https://www.crwlr.software/blog/dealing-with-http-url-query-strings-in-php',
        );
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

    $respondedRequest = new RespondedRequest(new Request('GET', '/compression'), new Response(body: Utils::streamFor($data)));

    $cache = new FileCache(helper_cachedir());

    $cache->set($respondedRequest->cacheKey(), $respondedRequest);

    $uncompressedFileSize = filesize(helper_cachedir() . '/' . $respondedRequest->cacheKey());

    expect($uncompressedFileSize)->not()->toBeFalse();

    clearstatcache(); // Results of filesize() are cached. Clear that to get correct result for compressed file size.

    $cache->useCompression();

    $cache->set($respondedRequest->cacheKey(), $respondedRequest);

    $compressedFileSize = filesize(helper_cachedir() . '/' . $respondedRequest->cacheKey());

    /** @var int $uncompressedFileSize */

    expect($compressedFileSize)->not()->toBeFalse()
        ->and($compressedFileSize)->toBeLessThan($uncompressedFileSize)
        // Didn't want to check for exact numbers, because I guess they could be a bit different on different systems.
        // But thought the diff should at least be more than 30% for the test to succeed.
        ->and($uncompressedFileSize - $compressedFileSize)->toBeGreaterThan($uncompressedFileSize * 0.3);
});

it('gets compressed cache items', function () {
    $cache = new FileCache(helper_cachedir());

    $cache->useCompression();

    $respondedRequest = new RespondedRequest(
        new Request('GET', '/compression'),
        new Response(body: Utils::streamFor('Hello World')),
    );

    $cache->set($respondedRequest->cacheKey(), $respondedRequest);

    $retrievedCacheItem = $cache->get($respondedRequest->cacheKey());

    expect($retrievedCacheItem)->toBeInstanceOf(RespondedRequest::class)
        ->and(Http::getBodyString($retrievedCacheItem))->toBe('Hello World');
});

it('is also able to decode uncompressed cache files when useCompression() is used', function () {
    $cache = new FileCache(helper_cachedir());

    $respondedRequest = new RespondedRequest(new Request('GET', '/yo'), new Response(body: Utils::streamFor('Yo')));

    $cache->set($respondedRequest->cacheKey(), $respondedRequest);

    $retrievedCacheItem = $cache->get($respondedRequest->cacheKey());

    expect($retrievedCacheItem)
        ->toBeInstanceOf(RespondedRequest::class)
        ->and(Http::getBodyString($retrievedCacheItem))
        ->toBe('Yo');

    $cache->useCompression();

    $retrievedCacheItem = $cache->get($respondedRequest->cacheKey());

    expect($retrievedCacheItem)
        ->toBeInstanceOf(RespondedRequest::class)
        ->and(Http::getBodyString($retrievedCacheItem))
        ->toBe('Yo');
});

it('can also read compressed cache files, when useCompression() is not used', function () {
    $cache = new FileCache(helper_cachedir());

    $cache->useCompression();

    $respondedRequest = new RespondedRequest(new Request('GET', '/no'), new Response(body: Utils::streamFor('No')));

    $cache->set($respondedRequest->cacheKey(), $respondedRequest);

    $cache = new FileCache(helper_cachedir());

    $retrievedCacheItem = $cache->get($respondedRequest->cacheKey());

    expect($retrievedCacheItem)
        ->toBeInstanceOf(RespondedRequest::class)
        ->and(Http::getBodyString($retrievedCacheItem))
        ->toBe('No');
});

test('you can change the default ttl', function () {
    $cache = new FileCache(helper_cachedir());

    $cache->ttl(900);

    $respondedRequest = new RespondedRequest(
        new Request('GET', '/foo'),
        new Response(body: Utils::streamFor('bar')),
    );

    $cache->set($respondedRequest->cacheKey(), $respondedRequest);

    $cacheItem = helper_getCacheItemByKey($respondedRequest->cacheKey());

    expect($cacheItem)->toBeInstanceOf(CacheItem::class)
        ->and($cacheItem?->ttl)->toBe(900);
});

it('prolongs the time to live for a single item', function () {
    $cache = new FileCache(helper_cachedir());

    $cache->ttl(100);

    $respondedRequest = new RespondedRequest(new Request('GET', '/a'), new Response(body: Utils::streamFor('b')));

    $cache->set($respondedRequest->cacheKey(), $respondedRequest);

    $cacheItem = helper_getCacheItemByKey($respondedRequest->cacheKey());

    expect($cacheItem)->toBeInstanceOf(CacheItem::class)
        ->and($cacheItem?->ttl)->toBe(100);

    /** @var CacheItem $cacheItem */

    $cache->prolong($cacheItem->key(), 200);

    $cacheItem = helper_getCacheItemByKey($cacheItem->key());

    expect($cacheItem)->toBeInstanceOf(CacheItem::class)
        ->and($cacheItem?->ttl)->toBe(200);
});

it('prolongs the time to live for all items in the cache directory', function () {
    $cache = new FileCache(helper_cachedir());

    $respondedRequest = new RespondedRequest(new Request('GET', '/a'), new Response(body: Utils::streamFor('b')));

    $cache->set($key1 = $respondedRequest->cacheKey(), $respondedRequest, 100);

    $respondedRequest = new RespondedRequest(new Request('GET', '/c'), new Response(body: Utils::streamFor('d')));

    $cache->set($key2 = $respondedRequest->cacheKey(), $respondedRequest, 200);

    $respondedRequest = new RespondedRequest(new Request('GET', '/e'), new Response(body: Utils::streamFor('f')));

    $cache->set($key3 = $respondedRequest->cacheKey(), $respondedRequest, 300);

    $cacheItem = helper_getCacheItemByKey($key1);

    expect($cacheItem)->toBeInstanceOf(CacheItem::class)
        ->and($cacheItem?->ttl)->toBe(100);

    $cacheItem = helper_getCacheItemByKey($key2);

    expect($cacheItem)->toBeInstanceOf(CacheItem::class)
        ->and($cacheItem?->ttl)->toBe(200);

    $cacheItem = helper_getCacheItemByKey($key3);

    expect($cacheItem)->toBeInstanceOf(CacheItem::class)
        ->and($cacheItem?->ttl)->toBe(300);

    $cache->prolongAll(250);

    $cacheItem = helper_getCacheItemByKey($key1);

    expect($cacheItem)->toBeInstanceOf(CacheItem::class)
        ->and($cacheItem?->ttl)->toBe(250);

    $cacheItem = helper_getCacheItemByKey($key2);

    expect($cacheItem)->toBeInstanceOf(CacheItem::class)
        ->and($cacheItem?->ttl)->toBe(250);

    $cacheItem = helper_getCacheItemByKey($key3);

    // Prolonging sets the provided value, no matter if an item's previous ttl value was
    // higher than the new one.
    expect($cacheItem)->toBeInstanceOf(CacheItem::class)
        ->and($cacheItem?->ttl)->toBe(250);
});

test('the get() and has() methods delete an expired item, but prolong does not', function () {
    $cache = new FileCache(helper_cachedir());

    $resp = new RespondedRequest(new Request('GET', '/'), new Response());

    // with get()
    $cacheItem = new CacheItem($resp, $resp->cacheKey(), 10, (new DateTimeImmutable())->sub(new DateInterval('PT11S')));

    $cache->set($cacheItem->key(), $cacheItem);

    $cacheItem = $cache->get($cacheItem->key());

    expect($cacheItem)->toBeNull()
        ->and(file_exists(helper_cachedir($resp->cacheKey())))->toBeFalse();

    // with has()
    $cacheItem = new CacheItem($resp, $resp->cacheKey(), 10, (new DateTimeImmutable())->sub(new DateInterval('PT11S')));

    $cache->set($cacheItem->key(), $cacheItem);

    $cache->has($cacheItem->key());

    expect($cache->has($cacheItem->key()))->toBeFalse()
        ->and(file_exists(helper_cachedir($cacheItem->key())))->toBeFalse();

    // with prolong()
    $cache->set($cacheItem->key(), $cacheItem);

    $cache->prolong($cacheItem->key(), 20);

    expect($cache->has($cacheItem->key()))->toBeTrue()
        ->and(file_exists(helper_cachedir($cacheItem->key())))->toBeTrue();
});
