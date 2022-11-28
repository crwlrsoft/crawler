<?php

namespace Crwlr\Crawler\Steps\Filters\Enums;

use Crwlr\Url\Exceptions\InvalidUrlException;
use Crwlr\Url\Url;
use Exception;

enum UrlFilterRule
{
    case Scheme;

    case Host;

    case Domain;

    case Path;

    case PathStartsWith;

    case PathMatches;

    public function evaluate(string $url, string $needle): bool
    {
        try {
            return match ($this) {
                self::Scheme => Url::parse($url)->scheme() === $needle,
                self::Host => Url::parse($url)->host() === $needle,
                self::Domain => Url::parse($url)->domain() === $needle,
                self::Path => Url::parse($url)->path() === $needle,
                self::PathStartsWith => str_starts_with(Url::parse($url)->path() ?? '', $needle),
                self::PathMatches => preg_match($needle, Url::parse($url)->path() ?? '') === 1,
            };
        } catch (InvalidUrlException|Exception $exception) {
            return false;
        }
    }
}
