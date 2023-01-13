<?php

namespace tests\Loader\Http\Messages;

use Crwlr\Crawler\Loader\Http\Messages\RespondedRequest;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;

test('It can be created from request and response objects.', function () {
    $request = new Request('GET', '/');

    $response = new Response();

    $respondedRequest = new RespondedRequest($request, $response);

    expect($respondedRequest)->toBeInstanceOf(RespondedRequest::class);
});

test('Creating with a redirect response adds a redirect uri.', function ($statusCode) {
    $request = new Request('GET', '/');

    $response = new Response($statusCode);

    $respondedRequest = new RespondedRequest($request, $response);

    expect($respondedRequest->redirects())->toHaveCount(1);
})->with([300, 301, 302, 303, 304, 305, 307, 308]);

test('Creating with non redirect responses doesn\'t add a redirect uri.', function ($statusCode) {
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

test('The requested uri remains the same when the request was redirected.', function () {
    $request = new Request('GET', '/request-uri');

    $response = new Response(301, ['Location' => '/redirect-uri']);

    $respondedRequest = new RespondedRequest($request, $response);

    $respondedRequest->setResponse(new Response(200));

    expect($respondedRequest->requestedUri())->toBe('/request-uri');
});

test('When request was not redirected the effective uri equals the requested uri', function () {
    $request = new Request('GET', '/request-uri');

    $response = new Response(200);

    $respondedRequest = new RespondedRequest($request, $response);

    expect($respondedRequest->effectiveUri())->toBe('/request-uri');
});

test('When request was redirected the effective uri is the redirect uri', function () {
    $request = new Request('GET', '/request-uri');

    $response = new Response(301, ['Location' => '/redirect-uri']);

    $respondedRequest = new RespondedRequest($request, $response);

    $respondedRequest->setResponse(new Response(200));

    expect($respondedRequest->effectiveUri())->toBe('/redirect-uri');
});
