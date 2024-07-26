<?php

namespace Crwlr\Crawler\Steps\Refiners\Url;

use Crwlr\Url\Exceptions\InvalidUrlComponentException;
use Crwlr\Url\Url;
use Exception;

class WithPort extends AbstractUrlRefiner
{
    public function __construct(protected readonly int $port) {}

    protected function staticRefinerMethod(): string
    {
        return 'UrlRefiner::withPort()';
    }

    /**
     * @throws InvalidUrlComponentException|Exception
     */
    protected function refineUrl(Url $url): string
    {
        $url->port($this->port);

        return (string) $url;
    }
}
