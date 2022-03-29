<?php

namespace Crwlr\Crawler\Steps;

use Crwlr\Crawler\Steps\Html\CssSelector;
use Crwlr\Crawler\Steps\Html\DomQueryInterface;

class Html extends Dom
{
    protected function makeDefaultDomQueryInstance(string $query): DomQueryInterface
    {
        return new CssSelector($query);
    }
}
