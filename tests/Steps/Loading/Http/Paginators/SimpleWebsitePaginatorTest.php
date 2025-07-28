<?php

namespace tests\Steps\Loading\Http\Paginators;

use Crwlr\Crawler\Loader\Http\Messages\RespondedRequest;
use Crwlr\Crawler\Logger\CliLogger;
use Crwlr\Crawler\Steps\Loading\Http\Paginators\SimpleWebsitePaginator;
use Crwlr\Crawler\Steps\Loading\Http\Paginators\StopRules\PaginatorStopRules;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;

use function tests\helper_getRespondedRequest;

function helper_getRespondedRequestWithResponseBody(string $urlPath, string $body): RespondedRequest
{
    return helper_getRespondedRequest(url: 'https://www.example.com' . $urlPath, responseBody: $body);
}

/**
 * @param array<string, string> $links
 */
function helper_createResponseBodyWithPaginationLinks(array $links): string
{
    $body = '<div class="pagination">';

    foreach ($links as $url => $text) {
        $body .= '<a href="' . $url . '">' . $text . '</a> ' . PHP_EOL;
    }

    return $body . '</div>';
}

/** @var TestCase $this */

it('says it has finished when no initial response was provided yet', function () {
    $paginator = new SimpleWebsitePaginator('.pagination');

    expect($paginator->hasFinished())->toBeTrue();
});

it('says it has finished when a response is provided, but it has no pagination links', function () {
    $paginator = new SimpleWebsitePaginator('.pagination', 3);

    $respondedRequest = helper_getRespondedRequestWithResponseBody('/listing', '<div class="listing"></div>');

    $paginator->processLoaded($respondedRequest->request, $respondedRequest);

    expect($paginator->hasFinished())->toBeTrue();
});

it('says it has not finished when an initial response with pagination links is provided', function () {
    $paginator = new SimpleWebsitePaginator('.pagination', 3);

    $responseBody = helper_createResponseBodyWithPaginationLinks([
        '/listing?page=1' => 'First page',
        '/listing?page=2' => 'Next page',
        '/listing?page12' => 'Last page',
    ]);

    $respondedRequest = helper_getRespondedRequestWithResponseBody('/listing', $responseBody);

    $paginator->processLoaded($respondedRequest->request, $respondedRequest);

    expect($paginator->hasFinished())->toBeFalse();
});

it('has finished when the loaded pages count exceeds the max pages limit', function () {
    $paginator = new SimpleWebsitePaginator('.pagination', 3);

    $responseBody = helper_createResponseBodyWithPaginationLinks([
        '/listing?page=1' => 'First page',
        '/listing?page=2' => 'Next page',
        '/listing?page12' => 'Last page',
    ]);

    $respondedRequest = helper_getRespondedRequestWithResponseBody('/listing', $responseBody);

    $paginator->processLoaded($respondedRequest->request, $respondedRequest);

    expect($paginator->hasFinished())->toBeFalse();

    $respondedRequest = helper_getRespondedRequestWithResponseBody('/listing?page=1', $responseBody);

    $paginator->processLoaded($respondedRequest->request, $respondedRequest);

    expect($paginator->hasFinished())->toBeFalse();

    $responseBody = helper_createResponseBodyWithPaginationLinks([
        '/listing?page=1' => 'First page',
        '/listing?page=3' => 'Next page',
        '/listing?page12' => 'Last page',
    ]);

    $respondedRequest = helper_getRespondedRequestWithResponseBody('/listing?page=2', $responseBody);

    $paginator->processLoaded($respondedRequest->request, $respondedRequest);

    expect($paginator->hasFinished())->toBeTrue();
});

it('says it has finished when there are no more found pagination links, that haven\'t been loaded yet', function () {
    $paginator = new SimpleWebsitePaginator('.pagination', 3);

    $responseBody = helper_createResponseBodyWithPaginationLinks(['/listing?page=2' => 'Page Two']);

    $respondedRequest = helper_getRespondedRequestWithResponseBody('/listing?page=1', $responseBody);

    $paginator->processLoaded($respondedRequest->request, $respondedRequest);

    expect($paginator->hasFinished())->toBeFalse();

    $paginator->getNextRequest();

    $responseBody = helper_createResponseBodyWithPaginationLinks(['/listing?page=2' => 'Page Two']);

    $respondedRequest = helper_getRespondedRequestWithResponseBody('/listing?page=2', $responseBody);

    $paginator->processLoaded($respondedRequest->request, $respondedRequest);

    expect($paginator->hasFinished())->toBeTrue();
});

it('finds pagination links when the selector matches the link itself', function () {
    $paginator = new SimpleWebsitePaginator('.nextPageLink', 3);

    $responseBody = '<a class="nextPageLink" href="/listing?page=2">Next Page</a>';

    $respondedRequest = helper_getRespondedRequestWithResponseBody('/listing?page=1', $responseBody);

    $paginator->processLoaded($respondedRequest->request, $respondedRequest);

    expect($paginator->getNextRequest()?->getUri()->__toString())->toBe('https://www.example.com/listing?page=2');
});

it('finds pagination links when the selected element is a wrapper for pagination links', function () {
    $paginator = new SimpleWebsitePaginator('.pagination', 3);

    $responseBody = '<div class="pagination"><a href="/listing?page=2">Next Page</a></div>';

    $respondedRequest = helper_getRespondedRequestWithResponseBody('/listing?page=1', $responseBody);

    $paginator->processLoaded($respondedRequest->request, $respondedRequest);

    expect($paginator->getNextRequest()?->getUri()->__toString())->toBe('https://www.example.com/listing?page=2');
});

it('finds all pagination links, when multiple elements match the pagination links selector', function () {
    $paginator = new SimpleWebsitePaginator('.pagination', 3);

    $responseBody = <<<HTML
        <div class="pagination"><a href="/listing?page=2">Next Page</a></div>
        <div class="pagination"><a href="/listing?page=12">Last Page</a></div>
        HTML;

    $respondedRequest = helper_getRespondedRequestWithResponseBody('/listing?page=1', $responseBody);

    $paginator->processLoaded($respondedRequest->request, $respondedRequest);

    expect($paginator->getNextRequest()?->getUri()->__toString())->toBe('https://www.example.com/listing?page=2')
        ->and($paginator->getNextRequest()?->getUri()->__toString())->toBe('https://www.example.com/listing?page=12');

});

it('logs that max pages limit was reached when it was reached', function () {
    $paginator = new SimpleWebsitePaginator('.pagination', 3);

    $responseBody = <<<HTML
        <div class="pagination">
            <a href="/listing?page=1">Page One</a>
            <a href="/listing?page=2">Page Two</a>
            <a href="/listing?page=3">Page Three</a>
            <a href="/listing?page=4">Page Four</a>
        </div>
        HTML;

    $respondedRequest = helper_getRespondedRequestWithResponseBody('/listing?page=1', $responseBody);

    $paginator->processLoaded($respondedRequest->request, $respondedRequest);

    $respondedRequest = helper_getRespondedRequestWithResponseBody('/listing?page=2', $responseBody);

    $paginator->processLoaded($respondedRequest->request, $respondedRequest);

    $respondedRequest = helper_getRespondedRequestWithResponseBody('/listing?page=3', $responseBody);

    $paginator->processLoaded($respondedRequest->request, $respondedRequest);

    expect($paginator->hasFinished())->toBeTrue();

    $paginator->logWhenFinished(new CliLogger());

    $output = $this->getActualOutputForAssertion();

    expect($output)->toContain('Max pages limit reached');
});

it('logs that all found pagination links have been loaded when max pages limit was not reached', function () {
    $paginator = new SimpleWebsitePaginator('.pagination', 3);

    $responseBody = <<<HTML
        <div class="pagination">
            <a href="/listing?page=1">Page One</a>
            <a href="/listing?page=2">Page Two</a>
            <a href="/listing?page=3">Page Three</a>
        </div>
        HTML;

    $respondedRequest = helper_getRespondedRequestWithResponseBody('/listing?page=1', $responseBody);

    $paginator->processLoaded($respondedRequest->request, $respondedRequest);

    $paginator->getNextRequest();

    $respondedRequest = helper_getRespondedRequestWithResponseBody('/listing?page=2', $responseBody);

    $paginator->logWhenFinished(new CliLogger());

    $paginator->processLoaded($respondedRequest->request, $respondedRequest);

    $paginator->logWhenFinished(new CliLogger());

    $paginator->getNextRequest();

    $respondedRequest = helper_getRespondedRequestWithResponseBody('/listing?page=3', $responseBody);

    $paginator->processLoaded($respondedRequest->request, $respondedRequest);

    expect($paginator->hasFinished())->toBeTrue();

    $paginator->logWhenFinished(new CliLogger());

    $output = $this->getActualOutputForAssertion();

    expect($output)
        ->not()->toContain('Max pages limit reached')
        ->and($output)
        ->toContain('All found pagination links loaded');
});

it(
    'always creates upcoming requests from the parent request, where a link was found (which does not have to be ' .
    'the latest processed response)',
    function () {
        $paginator = new SimpleWebsitePaginator('.pagination', 3);

        $responseBody = <<<HTML
        <div class="pagination">
            <a href="/list?page=1">Page One</a>
            <a href="/list?page=2">Page Two</a>
            <a href="/list?page=3">Page Three</a>
        </div>
        HTML;

        $respondedRequest = helper_getRespondedRequest(
            'GET',
            'https://www.example.com/list?page=1',
            ['foo' => 'bar'],
            responseBody: $responseBody,
        );

        $paginator->processLoaded($respondedRequest->request, $respondedRequest);

        $responseBody = <<<HTML
            <div class="pagination">
                <a href="/list?page=4">Page One</a>
                <a href="/list?page=5">Page Two</a>
                <a href="/list?page=6">Page Three</a>
            </div>
            HTML;

        $respondedRequest = helper_getRespondedRequest(
            'GET',
            'https://www.example.com/list?page=2',
            ['foo' => 'baz'],
            responseBody: $responseBody,
        );

        $paginator->processLoaded($respondedRequest->request, $respondedRequest);

        $nextRequest = $paginator->getNextRequest();

        expect($nextRequest?->getHeader('foo'))->toBe(['bar']);
    },
);

it('cleans up the stored parent requests always when getting the next request to load', function () {
    $paginator = new class ('.pagination') extends SimpleWebsitePaginator {
        /**
         * @return array<string, RequestInterface>
         */
        public function parentRequests(): array
        {
            return $this->parentRequests;
        }
    };

    $responseBody = <<<HTML
        <div class="pagination">
            <a href="/list?page=2">Page Two</a>
            <a href="/list?page=3">Page Three</a>
        </div>
        HTML;

    $respondedRequest = helper_getRespondedRequest(
        'GET',
        'https://www.example.com/list?page=1',
        ['foo' => 'bar'],
        responseBody: $responseBody,
    );

    $paginator->processLoaded($respondedRequest->request, $respondedRequest);

    expect(count($paginator->parentRequests()))->toBe(1);

    $nextRequest = $paginator->getNextRequest();

    if (!$nextRequest) {
        $this->fail('failed to get next request');
    }

    $respondedRequest = new RespondedRequest($nextRequest, new Response());

    $paginator->processLoaded($respondedRequest->request, $respondedRequest);

    expect(count($paginator->parentRequests()))->toBe(1);

    $nextRequest = $paginator->getNextRequest();

    if (!$nextRequest) {
        $this->fail('failed to get next request');
    }

    $respondedRequest = new RespondedRequest($nextRequest, new Response());

    $paginator->processLoaded($respondedRequest->request, $respondedRequest);

    expect(count($paginator->parentRequests()))->toBe(0);
});

it('does not stop, when a response does not meet the stop rule criterion', function () {
    $paginator = new SimpleWebsitePaginator('.pagination', 3);

    $paginator->stopWhen(PaginatorStopRules::contains('hello world'));

    $responseBody = helper_createResponseBodyWithPaginationLinks(['/listing?page=2' => 'Next page']);

    $respondedRequest = helper_getRespondedRequestWithResponseBody('/listing', $responseBody);

    $paginator->processLoaded($respondedRequest->request, $respondedRequest);

    expect($paginator->hasFinished())->toBeFalse();
});
