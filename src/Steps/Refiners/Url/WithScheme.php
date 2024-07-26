<?php

namespace Crwlr\Crawler\Steps\Refiners\Url;

use Crwlr\Url\Exceptions\InvalidUrlComponentException;
use Crwlr\Url\Url;
use Exception;

class WithScheme extends AbstractUrlRefiner
{
    public function __construct(protected readonly string $scheme) {}

    protected function staticRefinerMethod(): string
    {
        return 'UrlRefiner::withScheme()';
    }

    /**
     * @throws InvalidUrlComponentException|Exception
     */
    protected function refineUrl(Url $url): string
    {
        $url->scheme($this->scheme);

        return (string) $url;
    }
}
