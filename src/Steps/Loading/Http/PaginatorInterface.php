<?php

namespace Crwlr\Crawler\Steps\Loading\Http;

use Crwlr\Crawler\Loader\Http\Messages\RespondedRequest;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\UriInterface;
use Psr\Log\LoggerInterface;

interface PaginatorInterface
{
    public function hasFinished(): bool;

    public function getNextUrl(): ?string;

    public function prepareRequest(
        RequestInterface $request,
        ?RespondedRequest $previousResponse = null
    ): RequestInterface;

    public function processLoaded(
        UriInterface $url,
        RequestInterface $request,
        ?RespondedRequest $respondedRequest,
    ): void;

    public function logWhenFinished(LoggerInterface $logger): void;
}
