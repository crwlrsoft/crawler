<?php

namespace Crwlr\Crawler\Steps;

use Crwlr\Crawler\Cache\Exceptions\MissingZlibExtensionException;
use Crwlr\Crawler\Loader\Http\Messages\RespondedRequest;
use Crwlr\Crawler\Steps\Dom\XmlDocument;
use Crwlr\Crawler\Steps\Html\CssSelector;
use Crwlr\Crawler\Steps\Html\DomQuery;
use Crwlr\Crawler\Steps\Html\Exceptions\InvalidDomQueryException;

class Xml extends Dom
{
    /**
     * @throws InvalidDomQueryException
     */
    public function makeDefaultDomQueryInstance(string $query): DomQuery
    {
        return new CssSelector($query);
    }

    /**
     * @param mixed $input
     * @return XmlDocument
     * @throws MissingZlibExtensionException
     */
    protected function validateAndSanitizeInput(mixed $input): XmlDocument
    {
        if ($input instanceof RespondedRequest) {
            $this->baseUrl = $input->effectiveUri();
        }

        return $this->validateAndSanitizeToXmlDocumentInstance($input);
    }
}
