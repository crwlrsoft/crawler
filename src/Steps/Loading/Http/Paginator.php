<?php

namespace Crwlr\Crawler\Steps\Loading\Http;

use Crwlr\Crawler\Steps\Html\DomQueryInterface;
use Crwlr\Crawler\Steps\Loading\Http\Paginators\SimpleWebsitePaginator;

class Paginator
{
    public const MAX_PAGES_DEFAULT = 1000;

    public static function simpleWebsite(
        string|DomQueryInterface $paginationLinksSelector,
        int $maxPages = self::MAX_PAGES_DEFAULT,
    ): SimpleWebsitePaginator {
        return new SimpleWebsitePaginator($paginationLinksSelector, $maxPages);
    }
}