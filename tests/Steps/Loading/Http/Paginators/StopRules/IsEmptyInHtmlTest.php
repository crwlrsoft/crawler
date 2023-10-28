<?php

namespace tests\Steps\Loading\Http\Paginators\StopRules;

use Crwlr\Crawler\Loader\Http\Messages\RespondedRequest;
use Crwlr\Crawler\Steps\Loading\Http\Paginators\StopRules\PaginatorStopRules;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;

it('should stop, when called without a RespondedRequest object', function () {
    $rule = PaginatorStopRules::isEmptyInHtml('#list .item');

    expect($rule->shouldStop(new Request('GET', 'https://www.crwl.io/'), null))->toBeTrue();
});

it('should stop, when response is not HTML', function () {
    $rule = PaginatorStopRules::isEmptyInHtml('#list .item');

    $respondedRequest = new RespondedRequest(
        new Request('GET', 'https://www.crwl.io/'),
        new Response(body: '{ "foo": "bar" }'),
    );

    expect($rule->shouldStop($respondedRequest->request, $respondedRequest))->toBeTrue();
});

it('should stop, when the selector target does not exist in the HTML response', function () {
    $rule = PaginatorStopRules::isEmptyInHtml('#list');

    $respondedRequest = new RespondedRequest(
        new Request('GET', 'https://www.crwl.io/'),
        new Response(body: '<div id="foo"></div>'),
    );

    expect($rule->shouldStop($respondedRequest->request, $respondedRequest))->toBeTrue();
});

it('should stop, when the selector target is empty in the response', function () {
    $rule = PaginatorStopRules::isEmptyInHtml('#list');

    $respondedRequest = new RespondedRequest(
        new Request('GET', 'https://www.crwl.io/'),
        new Response(body: '<div id="list">  </div>'),
    );

    expect($rule->shouldStop($respondedRequest->request, $respondedRequest))->toBeTrue();
});

it('should not stop, when the selector target is not empty in the response', function () {
    $rule = PaginatorStopRules::isEmptyInHtml('#list');

    $respondedRequest = new RespondedRequest(
        new Request('GET', 'https://www.crwl.io/'),
        new Response(body: '<div id="list">a</div>'),
    );

    expect($rule->shouldStop($respondedRequest->request, $respondedRequest))->toBeFalse();

    // Also if the content is only child elements.
    $respondedRequest = new RespondedRequest(
        new Request('GET', 'https://www.crwl.io/'),
        new Response(body: '<div id="list"><span class="child"></span></div>'),
    );

    expect($rule->shouldStop($respondedRequest->request, $respondedRequest))->toBeFalse();
});
