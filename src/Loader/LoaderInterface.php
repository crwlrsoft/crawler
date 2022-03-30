<?php

namespace Crwlr\Crawler\Loader;

use Crwlr\Crawler\Loader\Http\Exceptions\LoadingException;
use InvalidArgumentException;

interface LoaderInterface
{
    /**
     * @param mixed $subject  The subject to load, whatever the Loader implementation needs to load something.
     * @return mixed
     * @throws InvalidArgumentException  Throw an InvalidArgumentException when the type of $subject argument isn't
     *                                   valid for the Loader implementation.
     */
    public function load(mixed $subject): mixed;

    /**
     * @throws InvalidArgumentException  Throw an InvalidArgumentException when the type of $subject argument isn't
     *                                   valid for the Loader implementation.
     * @throws LoadingException  Throw one when loading failed.
     */
    public function loadOrFail(mixed $subject): mixed;
}
