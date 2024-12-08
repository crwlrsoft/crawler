<?php

namespace tests\Steps\Dom\_Stubs;

use Crwlr\Crawler\Steps\Dom\HtmlElement;
use Crwlr\Crawler\Steps\Dom\Node;

class HtmlNodeStub extends Node
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
        return new HtmlElement($node);
    }
}
