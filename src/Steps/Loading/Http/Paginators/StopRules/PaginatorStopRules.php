<?php

namespace Crwlr\Crawler\Steps\Loading\Http\Paginators\StopRules;

use Crwlr\Crawler\Steps\Html\DomQueryInterface;

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

    public static function isEmptyInHtml(string|DomQueryInterface $selector): IsEmptyInHtml
    {
        return new IsEmptyInHtml($selector);
    }

    public static function isEmptyInXml(string|DomQueryInterface $selector): IsEmptyInXml
    {
        return new IsEmptyInXml($selector);
    }
}
