<?php

namespace Crwlr\Crawler\Loader;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

interface HttpLoaderInterface
{
    public function load(RequestInterface $request): ?ResponseInterface;
}
