<?php

namespace tests\Steps\Loading\Http\Paginators;

use Crwlr\Crawler\Loader\Http\Messages\RespondedRequest;
use Crwlr\Crawler\Steps\Loading\Http\Paginators\QueryParamsPaginator;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;

it('increases and decreases values in request url query params', function () {
    $paginator = QueryParamsPaginator::paramsInUrl()
        ->increase('page')
        ->increase('offset', 20)
        ->decrease('foo', 10)
        ->decrease('bar', 20);

    $request = new Request('GET', 'https://www.example.com/list?page=1&offset=20&foo=40&bar=10');

    $respondedRequest = new RespondedRequest($request, new Response());

    $paginator->processLoaded($request->getUri(), $request, $respondedRequest);

    $nextRequest = $paginator->getNextRequest();

    expect($nextRequest?->getUri()->__toString())->toBe('https://www.example.com/list?page=2&offset=40&foo=30&bar=-10');
});

it('increases and decreases values in query params in the body', function () {
    $paginator = QueryParamsPaginator::paramsInBody()
        ->increase('page')
        ->increase('offset', 20)
        ->decrease('foo', 10)
        ->decrease('bar', 20);

    $request = new Request('POST', 'https://www.example.com/list', body: 'page=1&offset=20&foo=40&bar=10');

    $respondedRequest = new RespondedRequest($request, new Response());

    $paginator->processLoaded($request->getUri(), $request, $respondedRequest);

    $nextRequest = $paginator->getNextRequest();

    expect($nextRequest?->getMethod())
        ->toBe('POST')
        ->and($nextRequest?->getUri()->__toString())
        ->toBe('https://www.example.com/list')
        ->and($nextRequest?->getBody()->getContents())
        ->toBe('page=2&offset=40&foo=30&bar=-10');
});
