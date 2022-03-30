<?php

namespace Crwlr\Crawler\Loader\Http\Cache\Exceptions;

use Exception;
use Psr\SimpleCache\CacheException;

class ReadingCacheFailedException extends Exception implements CacheException
{
}
