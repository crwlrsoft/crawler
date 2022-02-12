<?php

namespace Crwlr\Crawler\Loader;

use Crwlr\Crawler\Aggregates\RequestResponseAggregate;
use Crwlr\Crawler\Exceptions\LoadingException;
use Crwlr\Crawler\Loader\Traits\CheckRobotsTxt;
use Crwlr\Crawler\Loader\Traits\WaitPolitely;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\SimpleCache\InvalidArgumentException;

class PoliteHttpLoader extends HttpLoader
{
    use WaitPolitely, CheckRobotsTxt;

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
