<?php

namespace Crwlr\Crawler\Steps\Refiners\Url;

use Crwlr\Url\Exceptions\InvalidUrlComponentException;
use Crwlr\Url\Url;
use Exception;

class WithoutPort extends AbstractUrlRefiner
{
    protected function staticRefinerMethod(): string
    {
        return 'UrlRefiner::withoutPort()';
    }

    /**
     * @throws InvalidUrlComponentException|Exception
     */
    protected function refineUrl(Url $url): string
    {
        $url->resetPort();

        return (string) $url;
    }
}
