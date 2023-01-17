<?php

namespace tests\Loader\Http\Messages;

use Crwlr\Crawler\Loader\Http\Messages\RespondedRequest;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;

it('can be created from request and response objects.', function () {
    $request = new Request('GET', '/');

    $response = new Response();

    $respondedRequest = new RespondedRequest($request, $response);

    expect($respondedRequest)->toBeInstanceOf(RespondedRequest::class);
});

test('creating with a redirect response adds a redirect uri.', function ($statusCode) {
    $request = new Request('GET', '/');

    $response = new Response($statusCode);

    $respondedRequest = new RespondedRequest($request, $response);

    expect($respondedRequest->redirects())->toHaveCount(1);
})->with([300, 301, 302, 303, 304, 305, 307, 308]);

test('creating with non redirect responses doesn\'t add a redirect uri.', function ($statusCode) {
    $request = new Request('GET', '/');

    $response = new Response($statusCode);

    $respondedRequest = new RespondedRequest($request, $response);

    expect($respondedRequest->redirects())->toHaveCount(0);
})->with([101, 200, 404, 500]);

test('isRedirect returns false when the response is not a redirect', function () {
    $request = new Request('GET', '/');

    $response = new Response(200);

    $respondedRequest = new RespondedRequest($request, $response);

    expect($respondedRequest->isRedirect())->toBeFalse();
});

test('isRedirect returns true when the response is a redirect', function () {
    $request = new Request('GET', '/');

    $response = new Response(301);

    $respondedRequest = new RespondedRequest($request, $response);

    expect($respondedRequest->isRedirect())->toBeTrue();
});

test('isRedirect returns true when the last response is a redirect', function () {
    $request = new Request('GET', '/');

    $response = new Response(301);

    $respondedRequest = new RespondedRequest($request, $response);

    $respondedRequest->setResponse(new Response(302));

    expect($respondedRequest->isRedirect())->toBeTrue();
});

test('isRedirect returns false when the last response is not a redirect', function () {
    $request = new Request('GET', '/');

    $response = new Response(301);

    $respondedRequest = new RespondedRequest($request, $response);

    $respondedRequest->setResponse(new Response(200));

    expect($respondedRequest->isRedirect())->toBeFalse();
});

test('the requested uri remains the same when the request was redirected.', function () {
    $request = new Request('GET', '/request-uri');

    $response = new Response(301, ['Location' => '/redirect-uri']);

    $respondedRequest = new RespondedRequest($request, $response);

    $respondedRequest->setResponse(new Response(200));

    expect($respondedRequest->requestedUri())->toBe('/request-uri');
});

test('when request was not redirected the effective uri equals the requested uri', function () {
    $request = new Request('GET', '/request-uri');

    $response = new Response(200);

    $respondedRequest = new RespondedRequest($request, $response);

    expect($respondedRequest->effectiveUri())->toBe('/request-uri');
});

test('when request was redirected the effective uri is the redirect uri', function () {
    $request = new Request('GET', '/request-uri');

    $response = new Response(301, ['Location' => '/redirect-uri']);

    $respondedRequest = new RespondedRequest($request, $response);

    $respondedRequest->setResponse(new Response(200));

    expect($respondedRequest->effectiveUri())->toBe('/redirect-uri');
});

it('can be serialized', function () {
    $respondedRequest = new RespondedRequest(
        new Request('POST', '/home', ['key' => 'val'], 'bod'),
        new Response(201, ['k' => 'v'], 'res')
    );

    $respondedRequest->addRedirectUri('/index');

    $serialized = serialize($respondedRequest);

    expect($serialized)->toBe(
        'O:51:"Crwlr\Crawler\Loader\Http\Messages\RespondedRequest":8:{s:13:"requestMethod";s:4:"POST";s:10:' .
        '"requestUri";s:5:"/home";s:14:"requestHeaders";a:1:{s:3:"key";a:1:{i:0;s:3:"val";}}s:11:"requestBody";' .
        's:3:"bod";s:12:"effectiveUri";s:6:"/index";s:18:"responseStatusCode";i:201;s:15:"responseHeaders";a:1:{' .
        's:1:"k";a:1:{i:0;s:1:"v";}}s:12:"responseBody";s:3:"res";}'
    );
});

test('a serialized instance can be unserialized', function () {
    $serialized = 'O:51:"Crwlr\Crawler\Loader\Http\Messages\RespondedRequest":8:{s:13:"requestMethod";s:4:"POST";' .
        's:10:"requestUri";s:5:"/home";s:14:"requestHeaders";a:1:{s:3:"key";a:1:{i:0;s:3:"val";}}s:11:"requestBody";' .
        's:3:"bod";s:12:"effectiveUri";s:6:"/index";s:18:"responseStatusCode";i:201;s:15:"responseHeaders";a:1:{' .
        's:1:"k";a:1:{i:0;s:1:"v";}}s:12:"responseBody";s:3:"res";}';

    $respondedRequest = unserialize($serialized);

    /** @var RespondedRequest $respondedRequest */

    expect($respondedRequest)->toBeInstanceOf(RespondedRequest::class);

    expect($respondedRequest->request->getMethod())->toBe('POST');

    expect($respondedRequest->request->getUri()->__toString())->toBe('/home');

    expect($respondedRequest->request->getHeaders())->toBe(['key' => ['val']]);

    expect($respondedRequest->request->getBody()->getContents())->toBe('bod');

    expect($respondedRequest->effectiveUri())->toBe('/index');

    expect($respondedRequest->response->getStatusCode())->toBe(201);

    expect($respondedRequest->response->getHeaders())->toBe(['k' => ['v']]);

    expect($respondedRequest->response->getBody()->getContents())->toBe('res');
});

it('can be created from a serialized array', function () {
    $serialized = 'a:8:{s:13:"requestMethod";s:3:"GET";s:10:"requestUri";s:4:"/foo";s:14:"requestHeaders";a:0:{}s:11:' .
        '"requestBody";s:0:"";s:12:"effectiveUri";s:4:"/bar";s:18:"responseStatusCode";i:200;s:15:"responseHeaders";' .
        'a:0:{}s:12:"responseBody";s:0:"";}';

    $respondedRequest = RespondedRequest::fromArray(unserialize($serialized));

    expect($respondedRequest)->toBeInstanceOf(RespondedRequest::class);

    expect($respondedRequest->request->getUri()->__toString())->toBe('/foo');

    expect($respondedRequest->effectiveUri())->toBe('/bar');
});

it('makes a cache key from a Request object', function () {
    $request = new Request('GET', 'https://www.crwlr.software/packages', ['accept-encoding' => 'gzip, deflate, br']);

    expect(RespondedRequest::cacheKeyFromRequest($request))->toBe('fc2a9e78c97e68674201853cea4a3d74');

    $request = $request->withAddedHeader('accept-language', 'de-DE,de;q=0.9,en-US;q=0.8,en;q=0.7');

    expect(RespondedRequest::cacheKeyFromRequest($request))->not()->toBe('fc2a9e78c97e68674201853cea4a3d74');
});

test('when creating the key it ignores cookies in the sent headers', function () {
    $request = new Request('GET', 'https://www.crwlr.software/packages', ['accept-encoding' => 'gzip, deflate, br']);

    $keyWithoutCookie = RespondedRequest::cacheKeyFromRequest($request);

    $request = new Request('GET', 'https://www.crwlr.software/packages', [
        'accept-encoding' => 'gzip, deflate, br',
        'Cookie' => 'cookieName=v4lu3',
    ]);

    expect(RespondedRequest::cacheKeyFromRequest($request))->toBe($keyWithoutCookie);

    $request = new Request('GET', 'https://www.crwlr.software/packages', [
        'accept-encoding' => 'gzip, deflate, br',
        'cookie' => 'cookieName=v4lu3',
    ]);

    expect(RespondedRequest::cacheKeyFromRequest($request))->toBe($keyWithoutCookie);
});

it('generates a cache key for an instance', function () {
    $respondedRequest = new RespondedRequest(new Request('GET', '/foo/bar'), new Response());

    expect($respondedRequest->cacheKey())->toBe('27ca75942fb28ed0d8fb3f9b077dd582');
});
