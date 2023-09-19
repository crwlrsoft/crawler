<?php

namespace Crwlr\Crawler\Cache\Exceptions;

use Exception;
use Psr\SimpleCache\CacheException;

class MissingZlibExtensionException extends Exception implements CacheException {}
