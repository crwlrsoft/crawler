<?php

namespace Crwlr\Crawler\Loader\Http\Cache\Exceptions;

use Exception;
use Psr\SimpleCache\CacheException;

class MissingZlibExtensionException extends Exception implements CacheException
{
}
