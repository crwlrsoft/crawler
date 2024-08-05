<?php

namespace Crwlr\Crawler\Loader\Http\Exceptions;

use Exception;
use Psr\Http\Message\UriInterface;
use Throwable;

class LoadingException extends Exception
{
    public ?int $httpStatusCode = null;

    public static function from(Throwable $previousException): self
    {
        return new self(
            'Loading failed. Exception of type ' . get_class($previousException) . ' was thrown. Exception message: ' .
            $previousException->getMessage(),
            previous: $previousException,
        );
    }

    public static function make(string|UriInterface $uri, ?int $httpStatusCode = null): self
    {
        if ($uri instanceof UriInterface) {
            $uri = (string) $uri;
        }

        $message = 'Failed to load ' . $uri;

        if ($httpStatusCode !== null) {
            $message .= ' (' . $httpStatusCode . ').';
        } else {
            $message .= '.';
        }

        $instance = new self($message);

        if ($httpStatusCode !== null) {
            $instance->httpStatusCode = $httpStatusCode;
        }

        return $instance;
    }
}
