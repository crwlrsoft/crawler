<?php

namespace Crwlr\Crawler\Steps\Loading\Http\Paginators;

use Crwlr\Crawler\Loader\Http\Messages\RespondedRequest;
use Crwlr\Crawler\Steps\Loading\Http\Paginator;
use Crwlr\Crawler\Steps\Loading\Http\PaginatorInterface;
use Psr\Http\Message\RequestInterface;

abstract class AbstractPaginator implements PaginatorInterface
{
    public function __construct(protected int $maxPages = Paginator::MAX_PAGES_DEFAULT)
    {
    }

    public function prepareRequest(
        RequestInterface $request,
        ?RespondedRequest $previousResponse = null,
    ): RequestInterface {
        return $request;
    }
}
