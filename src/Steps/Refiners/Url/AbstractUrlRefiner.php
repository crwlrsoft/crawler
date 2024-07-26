<?php

namespace Crwlr\Crawler\Steps\Refiners\Url;

use Crwlr\Crawler\Steps\Refiners\AbstractRefiner;
use Crwlr\Url\Exceptions\InvalidUrlComponentException;
use Crwlr\Url\Url;
use Exception;
use Psr\Http\Message\UriInterface;

abstract class AbstractUrlRefiner extends AbstractRefiner
{
    /**
     * @throws InvalidUrlComponentException|Exception
     */
    public function refine(mixed $value): mixed
    {
        if (!is_string($value) && !$value instanceof Url && !$value instanceof UriInterface) {
            $this->logTypeWarning($this->staticRefinerMethod(), $value);

            return $value;
        }

        if (!$value instanceof Url) {
            $value = Url::parse($value);
        }

        return $this->refineUrl($value);
    }

    abstract protected function staticRefinerMethod(): string;

    abstract protected function refineUrl(Url $url): string;
}
