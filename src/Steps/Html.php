<?php

namespace Crwlr\Crawler\Steps;

use Crwlr\Crawler\Cache\Exceptions\MissingZlibExtensionException;
use Crwlr\Crawler\Loader\Http\Messages\RespondedRequest;
use Crwlr\Crawler\Steps\Dom\HtmlDocument;
use Crwlr\Crawler\Steps\Html\CssSelector;
use Crwlr\Crawler\Steps\Html\DomQuery;
use Crwlr\Crawler\Steps\Html\Exceptions\InvalidDomQueryException;
use Crwlr\Crawler\Steps\Html\GetLink;
use Crwlr\Crawler\Steps\Html\GetLinks;
use Crwlr\Crawler\Steps\Html\MetaData;
use Crwlr\Crawler\Steps\Html\SchemaOrg;

class Html extends Dom
{
    /**
     * @throws InvalidDomQueryException
     */
    public static function getLink(?string $selector = null): GetLink
    {
        return new GetLink($selector);
    }

    /**
     * @throws InvalidDomQueryException
     */
    public static function getLinks(?string $selector = null): GetLinks
    {
        return new GetLinks($selector);
    }

    public static function metaData(): MetaData
    {
        return new MetaData();
    }

    public static function schemaOrg(): SchemaOrg
    {
        return new SchemaOrg();
    }

    /**
     * @param mixed $input
     * @return HtmlDocument
     * @throws MissingZlibExtensionException
     */
    protected function validateAndSanitizeInput(mixed $input): HtmlDocument
    {
        if ($input instanceof RespondedRequest) {
            $this->baseUrl = $input->effectiveUri();
        }

        return $this->validateAndSanitizeToHtmlDocumentInstance($input);
    }

    /**
     * @throws InvalidDomQueryException
     */
    protected function makeDefaultDomQueryInstance(string $query): DomQuery
    {
        return new CssSelector($query);
    }
}
