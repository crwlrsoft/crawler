<?php

namespace Crwlr\Crawler\Loader;

use Crwlr\Crawler\UserAgent;
use Crwlr\Url\Exceptions\InvalidUrlException;
use Crwlr\Url\Url;
use GuzzleHttp\Psr7\Request;
use InvalidArgumentException;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Throwable;

class HttpLoader extends Loader implements LoaderInterface
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

    public function load(mixed $subject): ?ResponseInterface
    {
        $request = $this->validateSubjectType($subject);

        if (!$this->isAllowedToBeLoaded($request->getUri())) {
            return null;
        }

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
    public function loadOrFail(mixed $subject): ResponseInterface
    {
        $request = $this->validateSubjectType($subject);
        $this->isAllowedToBeLoaded($request->getUri(), true);
        $request = $request->withHeader('User-Agent', $this->userAgent->__toString());
        $this->trackRequestStart();
        $response = $this->httpClient->sendRequest($request);
        $this->trackRequestEnd();
        $this->callHook('onSuccess', $request, $response);
        $this->callHook('afterLoad', $request);

        return $response;
    }

    /**
     * @throws InvalidArgumentException
     * @throws InvalidUrlException
     */
    protected function validateSubjectType(RequestInterface|string $requestOrUri): RequestInterface
    {
        if (is_string($requestOrUri)) {
            return new Request('GET', Url::parsePsr7($requestOrUri));
        }

        return $requestOrUri;
    }
}
