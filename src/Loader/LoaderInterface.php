<?php

namespace Crwlr\Crawler\Loader;

use Crwlr\Crawler\Loader\Http\Exceptions\LoadingException;
use InvalidArgumentException;
use Psr\SimpleCache\CacheInterface;

interface LoaderInterface
{
    /**
     * @param mixed $subject  The subject to load, whatever the Loader implementation needs to load something.
     * @return mixed
     */
    public function load(mixed $subject): mixed;

    /**
     * @throws InvalidArgumentException  Throw an InvalidArgumentException when the type of $subject argument isn't
     *                                   valid for the Loader implementation.
     * @throws LoadingException  Throw one when loading failed.
     */
    public function loadOrFail(mixed $subject): mixed;

    /**
     * Add an implementation of the PSR-16 CacheInterface that the Loader will use to cache loaded resources.
     */
    public function setCache(CacheInterface $cache): static;
}
