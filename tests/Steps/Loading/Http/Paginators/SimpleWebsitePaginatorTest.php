<?php

namespace tests\Steps\Loading\Http\Paginators;

use Crwlr\Crawler\Loader\Http\Messages\RespondedRequest;
use Crwlr\Crawler\Logger\CliLogger;
use Crwlr\Crawler\Steps\Loading\Http\Paginators\SimpleWebsitePaginator;
use Crwlr\Url\Url;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Utils;
use PHPUnit\Framework\TestCase;

function helper_getRespondedRequestWithResponseBody(string $urlPath, string $body): RespondedRequest
{
    return new RespondedRequest(
        new Request('GET', 'https://www.example.com' . $urlPath),
        new Response(200, body: Utils::streamFor($body)),
    );
}

/**
 * @param array<string, string> $links
 */
function helper_createResponseBodyWithPaginationLinks(array $links): string
{
    $body = '<div class="pagination">';

    foreach ($links as $url => $text) {
        $body .= '<a href="' . $url .'">' . $text . '</a> ' . PHP_EOL;
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

    $respondedRequest = helper_getRespondedRequestWithResponseBody('listing', '<div class="listing"></div>');

    $paginator->processLoaded(
        $respondedRequest->request->getUri(),
        $respondedRequest->request,
        $respondedRequest,
    );

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

    $paginator->processLoaded(
        Url::parsePsr7('https://www.example.com/listing'),
        $respondedRequest->request,
        $respondedRequest,
    );

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

    $paginator->processLoaded(
        Url::parsePsr7('https://www.example.com/listing'),
        $respondedRequest->request,
        $respondedRequest,
    );

    expect($paginator->hasFinished())->toBeFalse();

    $respondedRequest = helper_getRespondedRequestWithResponseBody('/listing?page=1', $responseBody);

    $paginator->processLoaded(
        Url::parsePsr7('https://www.example.com/listing?page=1'),
        $respondedRequest->request,
        $respondedRequest,
    );

    expect($paginator->hasFinished())->toBeFalse();

    $responseBody = helper_createResponseBodyWithPaginationLinks([
        '/listing?page=1' => 'First page',
        '/listing?page=3' => 'Next page',
        '/listing?page12' => 'Last page',
    ]);

    $respondedRequest = helper_getRespondedRequestWithResponseBody('/listing?page=2', $responseBody);

    $paginator->processLoaded(
        Url::parsePsr7('https://www.example.com/listing?page=2'),
        $respondedRequest->request,
        $respondedRequest,
    );

    expect($paginator->hasFinished())->toBeTrue();
});

it('says it has finished when there are no more found pagination links, that haven\'t been loaded yet', function () {
    $paginator = new SimpleWebsitePaginator('.pagination', 3);

    $responseBody = helper_createResponseBodyWithPaginationLinks(['/listing?page=2' => 'Page Two']);

    $respondedRequest = helper_getRespondedRequestWithResponseBody('/listing?page=1', $responseBody);

    $paginator->processLoaded($respondedRequest->request->getUri(), $respondedRequest->request, $respondedRequest);

    expect($paginator->hasFinished())->toBeFalse();

    $paginator->getNextUrl();

    $responseBody = helper_createResponseBodyWithPaginationLinks(['/listing?page=2' => 'Page Two']);

    $respondedRequest = helper_getRespondedRequestWithResponseBody('/listing?page=2', $responseBody);

    $paginator->processLoaded($respondedRequest->request->getUri(), $respondedRequest->request, $respondedRequest);

    expect($paginator->hasFinished())->toBeTrue();
});

it('finds pagination links when the selector matches the link itself', function () {
    $paginator = new SimpleWebsitePaginator('.nextPageLink', 3);

    $responseBody = '<a class="nextPageLink" href="/listing?page=2">Next Page</a>';

    $respondedRequest = helper_getRespondedRequestWithResponseBody('/listing?page=1', $responseBody);

    $paginator->processLoaded($respondedRequest->request->getUri(), $respondedRequest->request, $respondedRequest);

    expect($paginator->getNextUrl())->toBe('https://www.example.com/listing?page=2');
});

it('finds pagination links when the selected element is a wrapper for pagination links', function () {
    $paginator = new SimpleWebsitePaginator('.pagination', 3);

    $responseBody = '<div class="pagination"><a href="/listing?page=2">Next Page</a></div>';

    $respondedRequest = helper_getRespondedRequestWithResponseBody('/listing?page=1', $responseBody);

    $paginator->processLoaded($respondedRequest->request->getUri(), $respondedRequest->request, $respondedRequest);

    expect($paginator->getNextUrl())->toBe('https://www.example.com/listing?page=2');
});

it('finds all pagination links, when multiple elements match the pagination links selector', function () {
    $paginator = new SimpleWebsitePaginator('.pagination', 3);

    $responseBody = <<<HTML
        <div class="pagination"><a href="/listing?page=2">Next Page</a></div>
        <div class="pagination"><a href="/listing?page=12">Last Page</a></div>
        HTML;

    $respondedRequest = helper_getRespondedRequestWithResponseBody('/listing?page=1', $responseBody);

    $paginator->processLoaded($respondedRequest->request->getUri(), $respondedRequest->request, $respondedRequest);

    expect($paginator->getNextUrl())->toBe('https://www.example.com/listing?page=2');

    expect($paginator->getNextUrl())->toBe('https://www.example.com/listing?page=12');
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

    $paginator->processLoaded($respondedRequest->request->getUri(), $respondedRequest->request, $respondedRequest);

    $respondedRequest = helper_getRespondedRequestWithResponseBody('/listing?page=2', $responseBody);

    $paginator->processLoaded($respondedRequest->request->getUri(), $respondedRequest->request, $respondedRequest);

    $respondedRequest = helper_getRespondedRequestWithResponseBody('/listing?page=3', $responseBody);

    $paginator->processLoaded($respondedRequest->request->getUri(), $respondedRequest->request, $respondedRequest);

    expect($paginator->hasFinished())->toBeTrue();

    $paginator->logWhenFinished(new CliLogger());

    $output = $this->getActualOutput();

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

    $paginator->processLoaded($respondedRequest->request->getUri(), $respondedRequest->request, $respondedRequest);

    $paginator->getNextUrl();

    $respondedRequest = helper_getRespondedRequestWithResponseBody('/listing?page=2', $responseBody);

    $paginator->processLoaded($respondedRequest->request->getUri(), $respondedRequest->request, $respondedRequest);

    $paginator->getNextUrl();

    $respondedRequest = helper_getRespondedRequestWithResponseBody('/listing?page=3', $responseBody);

    $paginator->processLoaded($respondedRequest->request->getUri(), $respondedRequest->request, $respondedRequest);

    expect($paginator->hasFinished())->toBeTrue();

    $paginator->logWhenFinished(new CliLogger());

    $output = $this->getActualOutput();

    expect($output)->not()->toContain('Max pages limit reached');

    expect($output)->toContain('All found pagination links loaded');
});
