<?php

namespace tests\Loader\Http\Messages;

use Crwlr\Crawler\Loader\Http\Messages\RespondedRequest;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;

test('It can be created from request and response objects.', function () {
    $request = new Request('GET', '/');
    $response = new Response();
    $aggregate = new RespondedRequest($request, $response);
    expect($aggregate)->toBeInstanceOf(RespondedRequest::class);
});

test('Creating with a redirect response adds a redirect uri.', function ($statusCode) {
    $request = new Request('GET', '/');
    $response = new Response($statusCode);
    $aggregate = new RespondedRequest($request, $response);
    expect($aggregate->redirects())->toHaveCount(1);
})->with([300, 301, 302, 303, 304, 305, 307, 308]);

test('Creating with non redirect responses doesn\'t add a redirect uri.', function ($statusCode) {
    $request = new Request('GET', '/');
    $response = new Response($statusCode);
    $aggregate = new RespondedRequest($request, $response);
    expect($aggregate->redirects())->toHaveCount(0);
})->with([101, 200, 404, 500]);

test('isRedirect returns false when the response is not a redirect', function () {
    $request = new Request('GET', '/');
    $response = new Response(200);
    $aggregate = new RespondedRequest($request, $response);
    expect($aggregate->isRedirect())->toBeFalse();
});

test('isRedirect returns true when the response is a redirect', function () {
    $request = new Request('GET', '/');
    $response = new Response(301);
    $aggregate = new RespondedRequest($request, $response);
    expect($aggregate->isRedirect())->toBeTrue();
});

test('isRedirect returns true when the last response is a redirect', function () {
    $request = new Request('GET', '/');
    $response = new Response(301);
    $aggregate = new RespondedRequest($request, $response);
    $aggregate->setResponse(new Response(302));
    expect($aggregate->isRedirect())->toBeTrue();
});

test('isRedirect returns false when the last response is not a redirect', function () {
    $request = new Request('GET', '/');
    $response = new Response(301);
    $aggregate = new RespondedRequest($request, $response);
    $aggregate->setResponse(new Response(200));
    expect($aggregate->isRedirect())->toBeFalse();
});

test('The requested uri remains the same when the request was redirected.', function () {
    $request = new Request('GET', '/request-uri');
    $response = new Response(301, ['Location' => '/redirect-uri']);
    $aggregate = new RespondedRequest($request, $response);
    $aggregate->setResponse(new Response(200));
    expect($aggregate->requestedUri())->toBe('/request-uri');
});

test('When request was not redirected the effective uri equals the requested uri', function () {
    $request = new Request('GET', '/request-uri');
    $response = new Response(200);
    $aggregate = new RespondedRequest($request, $response);
    expect($aggregate->effectiveUri())->toBe('/request-uri');
});

test('When request was redirected the effective uri is the redirect uri', function () {
    $request = new Request('GET', '/request-uri');
    $response = new Response(301, ['Location' => '/redirect-uri']);
    $aggregate = new RespondedRequest($request, $response);
    $aggregate->setResponse(new Response(200));
    expect($aggregate->effectiveUri())->toBe('/redirect-uri');
});
