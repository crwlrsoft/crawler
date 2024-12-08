<?php

namespace Crwlr\Crawler\Steps\Dom;

use Dom\Document;
use Symfony\Component\DomCrawler\Crawler;

abstract class DomDocument extends Node
{
    public function __construct(string $source)
    {
        parent::__construct($this->makeDocumentInstance($source)); // @phpstan-ignore-line
    }

    /**
     * @param string $source
     * @return Document|Crawler
     */
    abstract protected function makeDocumentInstance(string $source): object;
}
