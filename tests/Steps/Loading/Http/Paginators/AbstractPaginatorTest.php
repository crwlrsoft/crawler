<?php

namespace tests\Steps\Loading\Http\Paginators;

use Crwlr\Crawler\Loader\Http\Messages\RespondedRequest;
use Crwlr\Crawler\Steps\Loading\Http\Paginators\StopRules\PaginatorStopRules;
use Crwlr\Url\Url;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use tests\_Stubs\AbstractTestPaginator;
use tests\_Stubs\DummyLogger;

use function tests\helper_getRespondedRequest;

test(
    'the temporary default implementation of the getNextRequest() method, uses the getNextUrl() method and the latest' .
    'request, to create the next request',
    function () {
        $paginator = new AbstractTestPaginator(nextUrl: 'https://www.example.com/bar');

        $respondedRequest = helper_getRespondedRequest(
            'POST',
            'https://www.example.com/foo',
            ['foo' => 'lorem ipsum'],
            'Helloooo'
        );

        $paginator->processLoaded($respondedRequest->request->getUri(), $respondedRequest->request, $respondedRequest);

        $nextRequest = $paginator->getNextRequest();

        expect($nextRequest?->getUri()->__toString())
            ->toBe('https://www.example.com/bar')
            ->and($nextRequest?->getMethod())
            ->toBe('POST')
            ->and($nextRequest?->getHeaders())
            ->toHaveKey('foo')
            ->and($nextRequest?->getHeaders()['foo'])
            ->toBe(['lorem ipsum'])
            ->and($nextRequest?->getBody()->getContents())
            ->toBe('Helloooo');
    }
);

it('registers loaded requests from PSR-7 RequestInterface instances', function () {
    $paginator = new AbstractTestPaginator(nextUrl: 'https://www.example.com/bar');

    $respondedRequest1 = helper_getRespondedRequest('GET', 'https://www.example.com/foo', [], 'Hi');

    $paginator->processLoaded($respondedRequest1->request->getUri(), $respondedRequest1->request, $respondedRequest1);

    expect($paginator->getLoaded())
        ->toBe(['f2be1fcc5667a8f4ee2fd7f48c69c909' => true])
        ->and($paginator->getLoadedCount())
        ->toBe(1)
        ->and($paginator->getLatestRequest())
        ->toBe($respondedRequest1->request);

    $respondedRequest2 = helper_getRespondedRequest('GET', 'https://www.example.com/bar', [], 'Yo');

    $paginator->processLoaded($respondedRequest2->request->getUri(), $respondedRequest2->request, $respondedRequest2);

    expect($paginator->getLoaded())->toBe([
        'f2be1fcc5667a8f4ee2fd7f48c69c909' => true,
        'd9e0c3987944f190782f5af9506eb478' => true,
    ])
        ->and($paginator->getLoadedCount())
        ->toBe(2)
        ->and($paginator->getLatestRequest())
        ->toBe($respondedRequest2->request);
});

it('registers loaded requests from RespondedRequest objects', function () {
    $paginator = new AbstractTestPaginator(nextUrl: 'https://www.example.com/bar');

    $requestOne = new Request('GET', Url::parsePsr7('https://www.example.com/foo'), [], 'Hi');

    $requestTwo = new Request('GET', Url::parsePsr7('https://www.example.com/bar'), [], 'Yo');

    $paginator->processLoaded($requestOne->getUri(), $requestOne, new RespondedRequest($requestTwo, new Response()));

    expect($paginator->getLoaded())
        ->toBe(['d9e0c3987944f190782f5af9506eb478' => true])
        ->and($paginator->getLoadedCount())
        ->toBe(1)
        ->and($paginator->getLatestRequest())
        ->toBe($requestTwo);
});

it('knows when the max pages to load limit is reached', function () {
    $paginator = new AbstractTestPaginator(3);

    $respondedRequest = helper_getRespondedRequest(url: 'https://www.example.com/foo');

    $paginator->processLoaded($respondedRequest->request->getUri(), $respondedRequest->request, $respondedRequest);

    expect($paginator->limitReached())->toBeFalse();

    $respondedRequest = helper_getRespondedRequest(url: 'https://www.example.com/bar');

    $paginator->processLoaded($respondedRequest->request->getUri(), $respondedRequest->request, $respondedRequest);

    expect($paginator->limitReached())->toBeFalse();

    $respondedRequest = helper_getRespondedRequest(url: 'https://www.example.com/baz');

    $paginator->processLoaded($respondedRequest->request->getUri(), $respondedRequest->request, $respondedRequest);

    expect($paginator->limitReached())->toBeTrue();

    expect($paginator->hasFinished())->toBeTrue();
});

test('the same request is not registered twice', function () {
    $paginator = new AbstractTestPaginator();

    $respondedRequest = helper_getRespondedRequest();

    $paginator->processLoaded($respondedRequest->request->getUri(), $respondedRequest->request, $respondedRequest);

    expect($paginator->getLoadedCount())->toBe(1);

    $respondedRequest = helper_getRespondedRequest();

    $paginator->processLoaded($respondedRequest->request->getUri(), $respondedRequest->request, $respondedRequest);

    expect($paginator->getLoadedCount())->toBe(1);
});

it('logs a message when the max pages limit was reached', function () {
    $paginator = new AbstractTestPaginator(2);

    $respondedRequest = helper_getRespondedRequest(url: 'https://www.example.com/foo');

    $paginator->processLoaded($respondedRequest->request->getUri(), $respondedRequest->request, $respondedRequest);

    $logger = new DummyLogger();

    $paginator->logWhenFinished($logger);

    expect($logger->messages[0])->toBe([
        'level' => 'info',
        'message' => 'Finished paginating.',
    ]);

    $respondedRequest = helper_getRespondedRequest(url: 'https://www.example.com/bar');

    $paginator->processLoaded($respondedRequest->request->getUri(), $respondedRequest->request, $respondedRequest);

    $paginator->logWhenFinished($logger);

    expect($logger->messages[1])->toBe([
        'level' => 'notice',
        'message' => 'Max pages limit reached.',
    ]);
});

it('logs a message when it finished paginating', function () {
    $paginator = new AbstractTestPaginator();

    $paginator->stopWhen(PaginatorStopRules::isEmptyResponse());

    $respondedRequest = helper_getRespondedRequest();

    $paginator->processLoaded($respondedRequest->request->getUri(), $respondedRequest->request, $respondedRequest);

    $logger = new DummyLogger();

    $paginator->logWhenFinished($logger);

    expect($logger->messages[0])->toBe([
        'level' => 'info',
        'message' => 'Finished paginating.',
    ]);
});

it('stops paginating when a stop condition is met', function () {
    $paginator = new AbstractTestPaginator();

    $paginator
        ->stopWhen(PaginatorStopRules::isEmptyResponse())
        ->stopWhen(PaginatorStopRules::isEmptyInJson('items'));

    $respondedRequest = helper_getRespondedRequest(
        url: 'https://www.example.com/list?page=1',
        responseBody: '{ "items": ["foo"] }',
    );

    $paginator->processLoaded($respondedRequest->request->getUri(), $respondedRequest->request, $respondedRequest);

    expect($paginator->hasFinished())->toBeFalse();

    $respondedRequest = helper_getRespondedRequest(url: 'https://www.example.com/list?page=2', responseBody: '{}');

    $paginator->processLoaded($respondedRequest->request->getUri(), $respondedRequest->request, $respondedRequest);

    expect($paginator->hasFinished())->toBeTrue();

    $paginator = new AbstractTestPaginator();

    $paginator
        ->stopWhen(PaginatorStopRules::isEmptyResponse())
        ->stopWhen(PaginatorStopRules::isEmptyInJson('items'));

    $respondedRequest = helper_getRespondedRequest(
        url: 'https://www.example.com/list?page=1',
        responseBody: '{ "items": [] }',
    );

    $paginator->processLoaded($respondedRequest->request->getUri(), $respondedRequest->request, $respondedRequest);

    expect($paginator->hasFinished())->toBeTrue();
});

test('after calling the setFinished() method, the hasFinished() method returns true', function () {
    $paginator = new AbstractTestPaginator();

    expect($paginator->hasFinished())->toBeFalse();

    $paginator->setFinished();

    expect($paginator->hasFinished())->toBeTrue();
});
