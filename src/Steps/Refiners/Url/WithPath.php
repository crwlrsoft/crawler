<?php

namespace Crwlr\Crawler\Steps\Refiners\Url;

use Crwlr\Url\Exceptions\InvalidUrlComponentException;
use Crwlr\Url\Url;
use Exception;

class WithPath extends AbstractUrlRefiner
{
    public function __construct(protected readonly string $path) {}

    protected function staticRefinerMethod(): string
    {
        return 'UrlRefiner::withPath()';
    }

    /**
     * @throws InvalidUrlComponentException|Exception
     */
    protected function refineUrl(Url $url): string
    {
        $url->path($this->path);

        return (string) $url;
    }
}
