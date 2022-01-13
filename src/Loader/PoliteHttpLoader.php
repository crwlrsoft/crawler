<?php

namespace Crwlr\Crawler\Loader;

use Crwlr\Crawler\Loader\Traits\CheckRobotsTxt;
use Crwlr\Crawler\Loader\Traits\WaitPolitely;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Message\ResponseInterface;

class PoliteHttpLoader extends HttpLoader
{
    use WaitPolitely, CheckRobotsTxt;

    public function load(mixed $subject): ?ResponseInterface
    {
        $this->waitUntilNextRequestCanBeSent();

        return parent::load($subject);
    }

    /**
     * @throws ClientExceptionInterface
     */
    public function loadOrFail(mixed $subject): ResponseInterface
    {
        $this->waitUntilNextRequestCanBeSent();

        return parent::loadOrFail($subject);
    }
}
