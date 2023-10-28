<?php

namespace Crwlr\Crawler\Steps\Loading\Http\Paginators\StopRules;

use Crwlr\Crawler\Loader\Http\Messages\RespondedRequest;
use Crwlr\Crawler\Steps\Loading\Http;
use Psr\Http\Message\RequestInterface;

class IsEmptyResponse implements StopRule
{
    public function shouldStop(RequestInterface $request, ?RespondedRequest $respondedRequest): bool
    {
        if (!$respondedRequest) {
            return true;
        }

        $content = trim(Http::getBodyString($respondedRequest->response));

        return $content === '' || $content === '[]' || $content === '{}';
    }
}
