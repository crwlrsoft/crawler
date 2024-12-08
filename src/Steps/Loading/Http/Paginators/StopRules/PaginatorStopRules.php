<?php

namespace Crwlr\Crawler\Steps\Loading\Http\Paginators\StopRules;

use Crwlr\Crawler\Steps\Html\DomQuery;

class PaginatorStopRules
{
    public static function isEmptyResponse(): IsEmptyResponse
    {
        return new IsEmptyResponse();
    }

    public static function isEmptyInJson(string $dotNotationKey): IsEmptyInJson
    {
        return new IsEmptyInJson($dotNotationKey);
    }

    public static function isEmptyInHtml(string|DomQuery $selector): IsEmptyInHtml
    {
        return new IsEmptyInHtml($selector);
    }

    public static function isEmptyInXml(string|DomQuery $selector): IsEmptyInXml
    {
        return new IsEmptyInXml($selector);
    }

    public static function contains(string $string): Contains
    {
        return new Contains($string);
    }

    public static function notContains(string $string): NotContains
    {
        return new NotContains($string);
    }
}
