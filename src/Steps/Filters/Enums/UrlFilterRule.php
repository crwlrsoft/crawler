<?php

namespace Crwlr\Crawler\Steps\Filters\Enums;

use Crwlr\Url\Exceptions\InvalidUrlException;
use Crwlr\Url\Url;

enum UrlFilterRule
{
    case Scheme;

    case Host;

    case Domain;

    case Path;

    case PathStartsWith;

    public function evaluate(string $url, string $needle): bool
    {
        try {
            return match ($this) {
                self::Scheme => Url::parse($url)->scheme() === $needle,
                self::Host => Url::parse($url)->host() === $needle,
                self::Domain => Url::parse($url)->domain() === $needle,
                self::Path => Url::parse($url)->path() === $needle,
                self::PathStartsWith => str_starts_with(Url::parse($url)->path() ?? '', $needle),
            };
        } catch (InvalidUrlException $exception) {
            return false;
        }
    }
}
