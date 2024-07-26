<?php

namespace Crwlr\Crawler\Steps\Refiners\Url;

use Crwlr\Url\Exceptions\InvalidUrlComponentException;
use Crwlr\Url\Url;
use Exception;

class WithFragment extends AbstractUrlRefiner
{
    public function __construct(protected readonly string $fragment) {}

    protected function staticRefinerMethod(): string
    {
        return 'UrlRefiner::withFragment()';
    }

    /**
     * @throws InvalidUrlComponentException|Exception
     */
    protected function refineUrl(Url $url): string
    {
        $url->fragment($this->fragment);

        return (string) $url;
    }
}
