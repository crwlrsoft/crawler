<?php

namespace Crwlr\Crawler\Cache\Exceptions;

use Exception;
use Psr\SimpleCache\InvalidArgumentException as SimpleCacheInvalidArgumentException;

class InvalidArgumentException extends Exception implements SimpleCacheInvalidArgumentException
{
}
