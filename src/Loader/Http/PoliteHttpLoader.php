<?php

namespace Crwlr\Crawler\Loader\Http;

use Crwlr\Crawler\Aggregates\RequestResponseAggregate;
use Crwlr\Crawler\Exceptions\LoadingException;
use Crwlr\Crawler\Loader\Http\Traits\CheckRobotsTxt;
use Crwlr\Crawler\Loader\Http\Traits\WaitPolitely;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\SimpleCache\InvalidArgumentException;

class PoliteHttpLoader extends HttpLoader
{
    use WaitPolitely;
    use CheckRobotsTxt;

    public function load(mixed $subject): ?RequestResponseAggregate
    {
        $this->waitUntilNextRequestCanBeSent();

        return parent::load($subject);
    }

    /**
     * @throws ClientExceptionInterface
     * @throws LoadingException
     * @throws InvalidArgumentException
     */
    public function loadOrFail(mixed $subject): RequestResponseAggregate
    {
        $this->waitUntilNextRequestCanBeSent();

        return parent::loadOrFail($subject);
    }
}
