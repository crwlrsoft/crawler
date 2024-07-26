<?php

namespace Crwlr\Crawler\Steps\Refiners;

use Crwlr\Crawler\Steps\Refiners\Url\WithFragment;
use Crwlr\Crawler\Steps\Refiners\Url\WithHost;
use Crwlr\Crawler\Steps\Refiners\Url\WithoutPort;
use Crwlr\Crawler\Steps\Refiners\Url\WithPath;
use Crwlr\Crawler\Steps\Refiners\Url\WithPort;
use Crwlr\Crawler\Steps\Refiners\Url\WithQuery;
use Crwlr\Crawler\Steps\Refiners\Url\WithScheme;

class UrlRefiner
{
    public static function withScheme(string $scheme): WithScheme
    {
        return new WithScheme($scheme);
    }

    public static function withHost(string $host): WithHost
    {
        return new WithHost($host);
    }

    public static function withPort(int $port): WithPort
    {
        return new WithPort($port);
    }

    public static function withoutPort(): WithoutPort
    {
        return new WithoutPort();
    }

    public static function withPath(string $path): WithPath
    {
        return new WithPath($path);
    }

    public static function withQuery(string $query): WithQuery
    {
        return new WithQuery($query);
    }

    public static function withoutQuery(): WithQuery
    {
        return new WithQuery('');
    }

    public static function withFragment(string $fragment): WithFragment
    {
        return new WithFragment($fragment);
    }

    public static function withoutFragment(): WithFragment
    {
        return new WithFragment('');
    }
}
