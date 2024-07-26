<?php

namespace Crwlr\Crawler\Steps\Refiners\Url;

use Crwlr\Url\Exceptions\InvalidUrlComponentException;
use Crwlr\Url\Url;
use Exception;

class WithHost extends AbstractUrlRefiner
{
    public function __construct(protected readonly string $host) {}

    protected function staticRefinerMethod(): string
    {
        return 'UrlRefiner::withHost()';
    }

    /**
     * @throws InvalidUrlComponentException|Exception
     */
    protected function refineUrl(Url $url): string
    {
        $url->host($this->host);

        return (string) $url;
    }
}
