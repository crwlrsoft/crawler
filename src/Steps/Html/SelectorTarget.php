<?php

namespace Crwlr\Crawler\Steps\Html;

enum SelectorTarget
{
    case Text;
    case Html;
    case InnerText;
    case Attribute;
    case OuterHtml;
}
