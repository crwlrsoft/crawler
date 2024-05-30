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

it('increases and decreases non first level (of query array) parameters using dot notation', function () {
    $paginator = QueryParamsPaginator::paramsInBody()
        ->increaseUsingDotNotation('pagination.page')
        ->increase('pagination.size', 5, true)
        ->decreaseUsingDotNotation('pagination2.page')
        ->decrease('pagination2.size', 5, true);

    $request = new Request(
        'POST',
        'https://www.example.com/list',
        body: 'pagination[page]=1&pagination[size]=25&pagination2[page]=1&pagination2[size]=25&foo=bar',
    );

    $respondedRequest = new RespondedRequest($request, new Response());

    $paginator->processLoaded($request->getUri(), $request, $respondedRequest);

    $nextRequest = $paginator->getNextRequest();

    expect($nextRequest?->getBody()->getContents())
        ->toBe(
            'pagination%5Bpage%5D=2&pagination%5Bsize%5D=30&pagination2%5Bpage%5D=0&pagination2%5Bsize%5D=20&foo=bar',
        );
});
