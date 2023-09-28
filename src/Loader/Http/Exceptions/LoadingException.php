<?php

namespace Crwlr\Crawler\Loader\Http\Exceptions;

use Exception;
use Throwable;

class LoadingException extends Exception
{
    public static function from(Throwable $previousException): self
    {
        return new self(
            'Loading failed. Exception of type ' . get_class($previousException) . ' was thrown. Exception message: ' .
            $previousException->getMessage(),
            previous: $previousException,
        );
    }
}
