<?php

namespace Crwlr\Crawler\Loader\Http;

use Crwlr\Crawler\Loader\Http\Messages\RespondedRequest;
use Crwlr\Crawler\Loader\Http\Exceptions\LoadingException;
use Crwlr\Crawler\Loader\Http\Traits\CheckRobotsTxt;
use Crwlr\Crawler\Loader\Http\Traits\WaitPolitely;
use HeadlessChromium\Exception\CommunicationException;
use HeadlessChromium\Exception\CommunicationException\CannotReadResponse;
use HeadlessChromium\Exception\CommunicationException\InvalidResponse;
use HeadlessChromium\Exception\CommunicationException\ResponseHasError;
use HeadlessChromium\Exception\NavigationExpired;
use HeadlessChromium\Exception\NoResponseAvailable;
use HeadlessChromium\Exception\OperationTimedOut;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\SimpleCache\InvalidArgumentException;
use Throwable;

class PoliteHttpLoader extends HttpLoader
{
    use WaitPolitely;
    use CheckRobotsTxt;

    public function load(mixed $subject): ?RespondedRequest
    {
        $this->waitUntilNextRequestCanBeSent();

        return parent::load($subject);
    }

    /**
     * @throws ClientExceptionInterface
     * @throws InvalidArgumentException
     * @throws LoadingException
     * @throws CommunicationException
     * @throws CannotReadResponse
     * @throws InvalidResponse
     * @throws ResponseHasError
     * @throws NavigationExpired
     * @throws NoResponseAvailable
     * @throws OperationTimedOut
     * @throws Throwable
     */
    public function loadOrFail(mixed $subject): RespondedRequest
    {
        $this->waitUntilNextRequestCanBeSent();

        return parent::loadOrFail($subject);
    }
}
