<?php

namespace Crwlr\Crawler\Steps\Loading\Http\Paginators\StopRules;

use Crwlr\Crawler\Cache\Exceptions\MissingZlibExtensionException;
use Crwlr\Crawler\Loader\Http\Messages\RespondedRequest;
use Crwlr\Crawler\Steps\Loading\Http;
use Psr\Http\Message\RequestInterface;

class NotContains implements StopRule
{
    public function __construct(protected string $contains) {}

    /**
     * @throws MissingZlibExtensionException
     */
    public function shouldStop(RequestInterface $request, ?RespondedRequest $respondedRequest): bool
    {
        if (!$respondedRequest) {
            return true;
        }

        $content = trim(Http::getBodyString($respondedRequest->response));

        return !str_contains($content, $this->contains);
    }
}
