<?php

namespace Crwlr\Crawler\Loader;

use Crwlr\Crawler\Loader\Traits\WaitPolitely;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class PoliteHttpLoader extends HttpLoader
{
    use WaitPolitely;

    public function load(RequestInterface $request): ?ResponseInterface
    {
        $this->waitUntilNextRequestCanBeSent();

        return parent::load($request);
    }

    /**
     * @throws ClientExceptionInterface
     */
    public function loadOrFail(RequestInterface $request): ResponseInterface
    {
        $this->waitUntilNextRequestCanBeSent();

        return parent::loadOrFail($request);
    }
}
