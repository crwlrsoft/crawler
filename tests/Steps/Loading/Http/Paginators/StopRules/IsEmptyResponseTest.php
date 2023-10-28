<?php

namespace tests\Steps\Loading\Http\Paginators\StopRules;

use Crwlr\Crawler\Loader\Http\Messages\RespondedRequest;
use Crwlr\Crawler\Steps\Loading\Http\Paginators\StopRules\PaginatorStopRules;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;

it('should stop, when no RespondedRequest object is provided', function () {
    $rule = PaginatorStopRules::isEmptyResponse();

    expect($rule->shouldStop(new Request('GET', 'https://www.crwl.io/'), null))->toBeTrue();
});

it('should stop, when the response body is empty', function () {
    $rule = PaginatorStopRules::isEmptyResponse();

    $respondedRequest = new RespondedRequest(
        new Request('GET', 'https://www.crwl.io/'),
        new Response(body: ''),
    );

    expect($rule->shouldStop($respondedRequest->request, $respondedRequest))->toBeTrue();
});

it('should stop, when the response body is only spaces', function () {
    $rule = PaginatorStopRules::isEmptyResponse();

    $respondedRequest = new RespondedRequest(
        new Request('GET', 'https://www.example.com/'),
        new Response(body: " \n\r\t "),
    );

    expect($rule->shouldStop($respondedRequest->request, $respondedRequest))->toBeTrue();
});

it('should stop, when the response body is an empty JSON array', function () {
    $rule = PaginatorStopRules::isEmptyResponse();

    $respondedRequest = new RespondedRequest(
        new Request('GET', 'https://www.crwlr.software/packages'),
        new Response(body: " [] "),
    );

    expect($rule->shouldStop($respondedRequest->request, $respondedRequest))->toBeTrue();
});

it('should stop, when the response body is an empty JSON object', function () {
    $rule = PaginatorStopRules::isEmptyResponse();

    $respondedRequest = new RespondedRequest(
        new Request('GET', 'https://www.crwl.io/en/home'),
        new Response(body: "{}"),
    );

    expect($rule->shouldStop($respondedRequest->request, $respondedRequest))->toBeTrue();
});
