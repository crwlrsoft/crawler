<?php

namespace Crwlr\Crawler\Steps\Refiners\Url;

use Crwlr\Url\Exceptions\InvalidUrlComponentException;
use Crwlr\Url\Url;
use Exception;

class WithQuery extends AbstractUrlRefiner
{
    public function __construct(protected readonly string $query) {}

    protected function staticRefinerMethod(): string
    {
        return 'UrlRefiner::withQuery()';
    }

    /**
     * @throws InvalidUrlComponentException|Exception
     */
    protected function refineUrl(Url $url): string
    {
        $url->query($this->query);

        return (string) $url;
    }
}
