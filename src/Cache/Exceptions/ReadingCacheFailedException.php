<?php

namespace Crwlr\Crawler\Cache\Exceptions;

use Exception;
use Psr\SimpleCache\CacheException;

class ReadingCacheFailedException extends Exception implements CacheException {}
