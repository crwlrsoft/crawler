<?php

namespace Crwlr\Crawler\Loader;

use Crwlr\Crawler\UserAgent;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Throwable;

class HttpLoader extends Loader implements HttpLoaderInterface
{
    protected ClientInterface $httpClient;

    public function __construct(UserAgent $userAgent, ClientInterface $httpClient, ?LoggerInterface $logger = null)
    {
        parent::__construct($userAgent, $logger);

        $this->httpClient = $httpClient;

        $this->onSuccess(function (RequestInterface $request, ResponseInterface $response, LoggerInterface $logger) {
            $logger->info('Loaded ' . $request->getUri()->__toString());
        });

        $this->onError(function (RequestInterface $request, $exception, $logger) {
            $logger->error(
                'Failed to load ' . $request->getUri()->__toString() . ': ' . $exception->getMessage()
            );
        });
    }

    public function load(RequestInterface $request): ?ResponseInterface
    {
        $request = $request->withHeader('User-Agent', $this->userAgent->__toString());
        $this->callHook('beforeLoad', $request);

        try {
            $this->trackRequestStart();
            $response = $this->httpClient->sendRequest($request);
            $this->trackRequestEnd(); // Don't move to finally so hooks don't run before it.
            $this->callHook('onSuccess', $request, $response);

            return $response;
        } catch (Throwable $exception) {
            $this->trackRequestEnd(); // Don't move to finally so hooks don't run before it.
            $this->callHook('onError', $request, $exception);

            return null;
        } finally {
            $this->callHook('afterLoad', $request);
        }
    }

    /**
     * @throws ClientExceptionInterface
     */
    public function loadOrFail(RequestInterface $request): ResponseInterface
    {
        $request = $request->withHeader('User-Agent', $this->userAgent->__toString());
        $this->trackRequestStart();
        $response = $this->httpClient->sendRequest($request);
        $this->trackRequestEnd();
        $this->callHook('onSuccess', $request, $response);
        $this->callHook('afterLoad', $request);

        return $response;
    }

    /**
     * Call a method that tracks when a request was sent when the WaitPolitely trait is used.
     */
    private function trackRequestStart(): void
    {
        if (method_exists($this, 'trackStartSendingRequest')) {
            $this->trackStartSendingRequest();
        }
    }

    /**
     * Call a method that tracks when a request was finished when the WaitPolitely trait is used.
     */
    private function trackRequestEnd(): void
    {
        if (method_exists($this, 'trackRequestFinished')) {
            $this->trackRequestFinished();
        }
    }
}
