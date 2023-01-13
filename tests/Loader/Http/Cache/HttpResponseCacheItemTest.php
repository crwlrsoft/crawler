<?php

namespace tests\Loader\Http\Cache;

use Crwlr\Crawler\Loader\Http\Messages\RespondedRequest;
use Crwlr\Crawler\Loader\Http\Cache\HttpResponseCacheItem;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;

it('can be created from primitive values.', function () {
    $item = new HttpResponseCacheItem('GET', '/', [], '', '', 200, [], '');

    expect($item)->toBeInstanceOf(HttpResponseCacheItem::class);
});

it('can be created from a RespondedRequest', function () {
    $respondedRequest = new RespondedRequest(new Request('GET', '/'), new Response());

    $item = HttpResponseCacheItem::fromRespondedRequest($respondedRequest);

    expect($item)->toBeInstanceOf(HttpResponseCacheItem::class);
});

it('can be created from an array', function () {
    $item = HttpResponseCacheItem::fromArray([
        'requestMethod' => 'GET',
        'requestUri' => '/',
        'requestHeaders' => [],
        'requestBody' => '',
        'effectiveUri' => '/',
        'responseStatusCode' => 200,
        'responseHeaders' => [],
        'responseBody' => '',
    ]);

    expect($item)->toBeInstanceOf(HttpResponseCacheItem::class);
});

it('can be created from a serialized array', function () {
    $serialized = 'a:8:{s:13:"requestMethod";s:3:"GET";s:10:"requestUri";s:1:"/";s:14:"requestHeaders";a:0:{}s:11:' .
        '"requestBody";s:0:"";s:12:"effectiveUri";s:1:"/";s:18:"responseStatusCode";i:200;s:15:"responseHeaders";' .
        'a:0:{}s:12:"responseBody";s:0:"";}';

    $item = HttpResponseCacheItem::fromSerialized($serialized);

    expect($item)->toBeInstanceOf(HttpResponseCacheItem::class);
});

it('makes a key from a Request object', function () {
    $request = new Request('GET', 'https://www.crwlr.software/packages', ['accept-encoding' => 'gzip, deflate, br']);

    expect(HttpResponseCacheItem::keyFromRequest($request))->toBe('fc2a9e78c97e68674201853cea4a3d74');

    $request = $request->withAddedHeader('accept-language', 'de-DE,de;q=0.9,en-US;q=0.8,en;q=0.7');

    expect(HttpResponseCacheItem::keyFromRequest($request))->not()->toBe('fc2a9e78c97e68674201853cea4a3d74');
});

test('When creating the key it ignores cookies in the sent headers', function () {
    $request = new Request('GET', 'https://www.crwlr.software/packages', ['accept-encoding' => 'gzip, deflate, br']);

    $keyWithoutCookie = HttpResponseCacheItem::keyFromRequest($request);

    $request = new Request('GET', 'https://www.crwlr.software/packages', [
        'accept-encoding' => 'gzip, deflate, br',
        'Cookie' => 'cookieName=v4lu3',
    ]);

    expect(HttpResponseCacheItem::keyFromRequest($request))->toBe($keyWithoutCookie);

    $request = new Request('GET', 'https://www.crwlr.software/packages', [
        'accept-encoding' => 'gzip, deflate, br',
        'cookie' => 'cookieName=v4lu3',
    ]);

    expect(HttpResponseCacheItem::keyFromRequest($request))->toBe($keyWithoutCookie);
});

test('When copying a HTTP message body it rewinds the stream before and after copying', function () {
    $request = new Request('GET', '/', [], 'request body');

    expect($request->getBody()->getContents())->toBe('request body');

    expect($request->getBody()->getContents())->toBe(''); // Stream cursor is at the end of the stream.

    expect(HttpResponseCacheItem::copyBody($request))->toBe('request body');

    expect($request->getBody()->getContents())->toBe('request body');
});

it('calculates its own key when created', function () {
    $item = new HttpResponseCacheItem('GET', '/', [], '', '/', 200, [], '');

    expect($item->key())->toBe('a905694a97e354eb73b9088c72e3a39c');
});

test('You can turn it into a plain array', function () {
    $item = new HttpResponseCacheItem('POST', '/yo', ['key' => 'val'], 'bod', '/yo1', 201, ['k' => 'v'], 'res');

    expect($item->toArray())->toBe([
        'requestMethod' => 'POST',
        'requestUri' => '/yo',
        'requestHeaders' => ['key' => 'val'],
        'requestBody' => 'bod',
        'effectiveUri' => '/yo1',
        'responseStatusCode' => 201,
        'responseHeaders' => ['k' => 'v'],
        'responseBody' => 'res',
    ]);
});

test('You can serialize it', function () {
    $item = new HttpResponseCacheItem('POST', '/yo', ['key' => 'val'], 'bod', '/yo1', 201, ['k' => 'v'], 'res');

    expect($item->serialize())->toBe(
        'a:8:{s:13:"requestMethod";s:4:"POST";s:10:"requestUri";s:3:"/yo";s:14:"requestHeaders";a:1:{s:3:"key";s:3:' .
        '"val";}s:11:"requestBody";s:3:"bod";s:12:"effectiveUri";s:4:"/yo1";s:18:"responseStatusCode";i:201;s:15:' .
        '"responseHeaders";a:1:{s:1:"k";s:1:"v";}s:12:"responseBody";s:3:"res";}'
    );
});

test('You can hydrate a RespondedRequest from it', function () {
    $respondedRequest = new RespondedRequest(
        new Request('GET', '/yo', ['key' => 'val'], 'request body'),
        new Response(301, ['Location' => '/yolo'])
    );

    $respondedRequest->setResponse(new Response(200, ['header' => 'value'], 'response body'));

    $item = HttpResponseCacheItem::fromRespondedRequest($respondedRequest);

    $hydratedRespondedRequest = $item->respondedRequest();

    expect($hydratedRespondedRequest->request->getMethod())->toBe('GET');

    expect($hydratedRespondedRequest->requestedUri())->toBe('/yo');

    expect($hydratedRespondedRequest->request->getUri()->__toString())->toBe('/yo');

    expect($hydratedRespondedRequest->request->getHeaders())->toBe(['key' => ['val']]);

    expect($hydratedRespondedRequest->request->getBody()->getContents())->toBe('request body');

    expect($hydratedRespondedRequest->effectiveUri())->toBe('/yolo');

    expect($hydratedRespondedRequest->response->getStatusCode())->toBe(200);

    expect($hydratedRespondedRequest->response->getHeaders())->toBe(['header' => ['value']]);

    expect($hydratedRespondedRequest->response->getBody()->getContents())->toBe('response body');
});

test('You can hydrate a Request object from it', function () {
    $respondedRequest = new RespondedRequest(
        new Request('GET', '/foo', ['bar' => 'baz'], 'request body'),
        new Response()
    );

    $item = HttpResponseCacheItem::fromRespondedRequest($respondedRequest);

    $hydratedRequest = $item->request();

    expect($hydratedRequest->getMethod())->toBe('GET');

    expect($hydratedRequest->getUri()->__toString())->toBe('/foo');

    expect($hydratedRequest->getHeaders())->toBe(['bar' => ['baz']]);

    expect($hydratedRequest->getBody()->getContents())->toBe('request body');
});

test('You can hydrate a Response object from it', function () {
    $response = new Response(404, ['header' => 'value'], 'response boddey');

    $respondedRequest = new RespondedRequest(new Request('GET', '/'), $response);

    $item = HttpResponseCacheItem::fromRespondedRequest($respondedRequest);

    $hydratedResponse = $item->response();

    expect($hydratedResponse->getStatusCode())->toBe(404);

    expect($hydratedResponse->getHeaders())->toBe(['header' => ['value']]);

    expect($hydratedResponse->getBody()->getContents())->toBe('response boddey');
});
