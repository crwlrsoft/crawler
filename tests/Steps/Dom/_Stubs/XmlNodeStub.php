<?php

namespace tests\Steps\Dom\_Stubs;

use Crwlr\Crawler\Steps\Dom\Node;
use Crwlr\Crawler\Steps\Dom\XmlElement;

class XmlNodeStub extends Node
{
    public function inner(): string
    {
        return $this->innerSource();
    }

    public function outer(): string
    {
        return $this->outerSource();
    }

    protected function makeChildNodeInstance(object $node): Node
    {
        return new XmlElement($node);
    }
}
