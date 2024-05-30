<?php

namespace tests\Steps\Loading\Http\Paginators\StopRules;

use Crwlr\Crawler\Loader\Http\Messages\RespondedRequest;
use Crwlr\Crawler\Steps\Loading\Http\Paginators\StopRules\PaginatorStopRules;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;

it('should stop, when called without a RespondedRequest object', function () {
    $rule = PaginatorStopRules::isEmptyInXml('channel item');

    expect($rule->shouldStop(new Request('GET', 'https://www.crwl.io/'), null))->toBeTrue();
});

it('should stop, when response is not XML', function () {
    $rule = PaginatorStopRules::isEmptyInXml('channel item');

    $respondedRequest = new RespondedRequest(
        new Request('GET', 'https://www.crwl.io/'),
        new Response(body: '{}'),
    );

    expect($rule->shouldStop($respondedRequest->request, $respondedRequest))->toBeTrue();
});

it('should stop, when the selector target does not exist in the XML response', function () {
    $rule = PaginatorStopRules::isEmptyInXml('channel item');

    $respondedRequest = new RespondedRequest(
        new Request('GET', 'https://www.crwl.io/'),
        new Response(body: '<?xml version="1.0" encoding="UTF-8" ?><rss version="2.0"><channel></channel></rss>'),
    );

    expect($rule->shouldStop($respondedRequest->request, $respondedRequest))->toBeTrue();
});

it('should stop, when the selector target is empty in the response', function () {
    $rule = PaginatorStopRules::isEmptyInXml('channel item');

    $respondedRequest = new RespondedRequest(
        new Request('GET', 'https://www.crwl.io/'),
        new Response(
            body: '<?xml version="1.0" encoding="UTF-8" ?><rss version="2.0"><channel><item>  </item></channel></rss>',
        ),
    );

    expect($rule->shouldStop($respondedRequest->request, $respondedRequest))->toBeTrue();
});

it('should not stop, when the selector target is not empty in the response', function () {
    $rule = PaginatorStopRules::isEmptyInXml('channel item');

    $respondedRequest = new RespondedRequest(
        new Request('GET', 'https://www.crwl.io/'),
        new Response(
            body: '<?xml version="1.0" encoding="UTF-8" ?><rss version="2.0"><channel><item>a</item></channel></rss>',
        ),
    );

    expect($rule->shouldStop($respondedRequest->request, $respondedRequest))->toBeFalse();

    // Also if the content is only child elements.
    $respondedRequest = new RespondedRequest(
        new Request('GET', 'https://www.crwl.io/'),
        new Response(
            body: '<?xml version="1.0" encoding="UTF-8" ?><rss version="2.0"><channel><item><foo></foo></item></channel></rss>',
        ),
    );

    expect($rule->shouldStop($respondedRequest->request, $respondedRequest))->toBeFalse();
});
