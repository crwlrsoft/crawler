<?php

namespace Crwlr\Crawler\Steps\Loading\Http\Paginators;

use Crwlr\Crawler\Loader\Http\Messages\RespondedRequest;
use Crwlr\Crawler\Steps\Loading\Http\Paginator;
use Crwlr\Crawler\Steps\Loading\Http\PaginatorInterface;
use Psr\Http\Message\RequestInterface;

/**
 * @deprecated There's a new improved version of this class: Crwlr\Crawler\Steps\Loading\Http\AbstractPaginator
 *             In order to prevent potentially breaking backwards compatibility, by adding methods and properties
 *             to this class, that could already exist in user's custom implementations, this class is deprecated
 *             and the improved version is available with a different namespace (see above).
 */

abstract class AbstractPaginator implements PaginatorInterface
{
    public function __construct(protected int $maxPages = Paginator::MAX_PAGES_DEFAULT) {}

    public function prepareRequest(
        RequestInterface $request,
        ?RespondedRequest $previousResponse = null,
    ): RequestInterface {
        return $request;
    }
}
