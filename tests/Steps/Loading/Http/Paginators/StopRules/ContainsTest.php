<?php

namespace tests\Steps\Loading\Http\Paginators\StopRules;

use Crwlr\Crawler\Loader\Http\Messages\RespondedRequest;
use Crwlr\Crawler\Steps\Loading\Http\Paginators\StopRules\PaginatorStopRules;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;

it('stops when called without a RespondedRequest object', function () {
    $rule = PaginatorStopRules::contains('foo');

    expect($rule->shouldStop(new Request('GET', 'https://www.example.com/foo'), null))->toBeTrue();
});

it('stops when the string is contained in the response body', function () {
    $rule = PaginatorStopRules::contains('foo');

    $respondedRequest = new RespondedRequest(
        new Request('GET', 'https://www.crwl.io/'),
        new Response(body: 'This string contains foo'),
    );

    expect($rule->shouldStop($respondedRequest->request, $respondedRequest))->toBeTrue();
});

it('does not stop when the string is not contained in the response body', function () {
    $rule = PaginatorStopRules::contains('foo');

    $respondedRequest = new RespondedRequest(
        new Request('GET', 'https://www.crwl.io/'),
        new Response(body: 'This does not contain the string'),
    );

    expect($rule->shouldStop($respondedRequest->request, $respondedRequest))->toBeFalse();
});
