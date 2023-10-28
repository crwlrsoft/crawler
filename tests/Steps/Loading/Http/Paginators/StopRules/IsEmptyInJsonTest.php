<?php

namespace tests\Steps\Loading\Http\Paginators\StopRules;

use Crwlr\Crawler\Loader\Http\Messages\RespondedRequest;
use Crwlr\Crawler\Steps\Loading\Http\Paginators\StopRules\PaginatorStopRules;
use Crwlr\Utils\Exceptions\InvalidJsonException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;

it('should stop, when called without a RespondedRequest object', function () {
    $rule = PaginatorStopRules::isEmptyInJson('data.items');

    expect($rule->shouldStop(new Request('GET', 'https://www.crwl.io/'), null))->toBeTrue();
});

it('throws an exception when response is not valid JSON', function () {
    $rule = PaginatorStopRules::isEmptyInJson('data.items');

    $respondedRequest = new RespondedRequest(
        new Request('GET', 'https://www.crwl.io/'),
        new Response(body: '<html></html>'),
    );

    expect($rule->shouldStop($respondedRequest->request, $respondedRequest))->toBeTrue();
})->throws(InvalidJsonException::class);

it('should stop, when the dot notation key does not exist in the response', function () {
    $rule = PaginatorStopRules::isEmptyInJson('data.items');

    $respondedRequest = new RespondedRequest(
        new Request('GET', 'https://www.crwl.io/'),
        new Response(body: '{ "data": { "foo": "bar" } }'),
    );

    expect($rule->shouldStop($respondedRequest->request, $respondedRequest))->toBeTrue();
});

it('should stop, when the dot notation key is empty in the response', function () {
    $rule = PaginatorStopRules::isEmptyInJson('data.items');

    $respondedRequest = new RespondedRequest(
        new Request('GET', 'https://www.crwl.io/'),
        new Response(body: '{ "data": { "items": [] } }'),
    );

    expect($rule->shouldStop($respondedRequest->request, $respondedRequest))->toBeTrue();
});

it('should not stop, when the dot notation key is not empty in the response', function () {
    $rule = PaginatorStopRules::isEmptyInJson('data.items');

    $respondedRequest = new RespondedRequest(
        new Request('GET', 'https://www.crwl.io/'),
        new Response(body: '{ "data": { "items": ["foo", "bar"] } }'),
    );

    expect($rule->shouldStop($respondedRequest->request, $respondedRequest))->toBeFalse();
});
