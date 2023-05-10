<?php

namespace tests\Steps\Loading\Http;

use Crwlr\Crawler\Loader\Http\Messages\RespondedRequest;
use Crwlr\Crawler\Steps\Loading\Http\Document;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Symfony\Component\DomCrawler\Crawler;

it('creates a symfony DomCrawler instance from a RespondedRequest', function () {
    $body = '<html><head><title>foo</title></head><body>hello</body></html>';

    $respondedRequest = new RespondedRequest(
        new Request('GET', 'https://www.example.com/foo'),
        new Response(200, body: $body),
    );

    $document = new Document($respondedRequest);

    expect($document->dom())->toBeInstanceOf(Crawler::class);

    expect($document->dom()->outerHtml())->toBe('<html><head><title>foo</title></head><body>hello</body></html>');
});

it('returns the effectiveUri as url()', function () {
    $body = '<html><head><title>foo</title><base href="/baz" /></head><body>hello</body></html>';

    $respondedRequest = new RespondedRequest(
        new Request('GET', 'https://www.example.com/foo'),
        new Response(301, ['Location' => 'https://www.example.com/bar'], $body),
    );

    $respondedRequest->addRedirectUri('https://www.example.com/bar');

    $document = new Document($respondedRequest);

    expect((string) $document->url())->toBe('https://www.example.com/bar');
});

it('returns the effectiveUri as baseUrl() if no base tag in HTML', function () {
    $respondedRequest = new RespondedRequest(
        new Request('GET', 'https://www.example.com/foo'),
        new Response(301, ['Location' => 'https://www.example.com/bar']),
    );

    $respondedRequest->addRedirectUri('https://www.example.com/bar');

    $document = new Document($respondedRequest);

    expect((string) $document->baseUrl())->toBe('https://www.example.com/bar');
});

it('returns the URL referenced in base tag as baseUrl()', function () {
    $body = '<html><head><title>foo</title><base href="/baz" /></head><body>hello</body></html>';

    $respondedRequest = new RespondedRequest(
        new Request('GET', 'https://www.example.com/foo'),
        new Response(301, ['Location' => 'https://www.example.com/bar'], $body),
    );

    $respondedRequest->addRedirectUri('https://www.example.com/bar');

    $document = new Document($respondedRequest);

    expect((string) $document->baseUrl())->toBe('https://www.example.com/baz');
});

it('returns the effectiveUri as canonicalUrl() if no canonical link in HTML', function () {
    $respondedRequest = new RespondedRequest(
        new Request('GET', 'https://www.example.com/foo'),
        new Response(301, ['Location' => 'https://www.example.com/bar']),
    );

    $respondedRequest->addRedirectUri('https://www.example.com/bar');

    $document = new Document($respondedRequest);

    expect($document->canonicalUrl())->toBe('https://www.example.com/bar');
});

it('returns the URL referenced in canonical link as canonicalUrl()', function () {
    $body = '<html><head><title>foo</title><link rel="canonical" href="/quz" /></head><body>hello</body></html>';

    $respondedRequest = new RespondedRequest(
        new Request('GET', 'https://www.example.com/foo'),
        new Response(301, ['Location' => 'https://www.example.com/bar'], $body),
    );

    $respondedRequest->addRedirectUri('https://www.example.com/bar');

    $document = new Document($respondedRequest);

    expect($document->canonicalUrl())->toBe('https://www.example.com/quz');
});
