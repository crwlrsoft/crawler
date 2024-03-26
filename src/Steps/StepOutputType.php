<?php

namespace Crwlr\Crawler\Steps;

enum StepOutputType
{
    case Scalar;

    case AssociativeArrayOrObject;

    case Mixed;
}
