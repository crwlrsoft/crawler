<?php

namespace tests\Utils;

use Crwlr\Crawler\Loader\Http\Messages\RespondedRequest;
use Crwlr\Crawler\Utils\RequestKey;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;

it('makes a cache key from a Request object', function () {
    $request = new Request('GET', 'https://www.crwlr.software/packages', ['accept-encoding' => 'gzip, deflate, br']);

    expect(RequestKey::from($request))->toBe('fc2a9e78c97e68674201853cea4a3d74');

    $request = $request->withAddedHeader('accept-language', 'de-DE,de;q=0.9,en-US;q=0.8,en;q=0.7');

    expect(RequestKey::from($request))->not()->toBe('fc2a9e78c97e68674201853cea4a3d74');
});

it('makes a cache key from a RespondedRequest object', function () {
    $respondedRequest = new RespondedRequest(
        new Request('GET', 'https://www.crwl.io/en/home', ['accept-encoding' => 'gzip, deflate, br']),
        new Response(),
    );

    expect(RequestKey::from($respondedRequest))->toBe('08bcc643c9fb21af5e4f3361243e2220');
});

test('when creating the key it ignores cookies in the sent headers by default', function () {
    $request = new Request('GET', 'https://www.crwlr.software/packages', ['accept-encoding' => 'gzip, deflate, br']);

    $keyWithoutCookie = RequestKey::from($request);

    $request = new Request('GET', 'https://www.crwlr.software/packages', [
        'accept-encoding' => 'gzip, deflate, br',
        'Cookie' => 'cookieName=v4lu3',
    ]);

    expect(RequestKey::from($request))->toBe($keyWithoutCookie);
});

it('also ignores other headers when provided in second parameter', function () {
    $request = new Request('GET', 'https://www.example.com', ['accept-encoding' => 'gzip, deflate, br']);

    $keyWithAcceptEncodingHeader = RequestKey::from($request);

    $keyWithoutAcceptEncodingHeader = RequestKey::from($request, ['accept-encoding']);

    expect($keyWithAcceptEncodingHeader)->not()->toBe($keyWithoutAcceptEncodingHeader);

    $request = new Request('GET', 'https://www.example.com', ['Accept-Encoding' => 'gzip']);

    $anotherKeyWithoutAcceptEncodingHeader = RequestKey::from($request, ['accept-encoding']);

    expect($keyWithoutAcceptEncodingHeader)->toBe($anotherKeyWithoutAcceptEncodingHeader);
});
