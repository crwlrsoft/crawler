<?php

namespace Crwlr\Crawler\Steps\Html;

enum SelectorTarget
{
    case Text;
    case FormattedText;
    case Html;
    case InnerText;
    case Attribute;
    case OuterHtml;
}
