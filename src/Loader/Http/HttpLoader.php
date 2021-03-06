<?php

namespace Crwlr\Crawler\Loader\Http;

use Crwlr\Crawler\Loader\Http\Messages\RespondedRequest;
use Crwlr\Crawler\Loader\Http\Cache\HttpResponseCacheItem;
use Crwlr\Crawler\Loader\Http\Exceptions\LoadingException;
use Crwlr\Crawler\Loader\Http\Cookies\CookieJar;
use Crwlr\Crawler\Loader\Loader;
use Crwlr\Crawler\UserAgents\UserAgentInterface;
use Crwlr\Url\Exceptions\InvalidUrlException;
use Crwlr\Url\Url;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use InvalidArgumentException;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Throwable;

class HttpLoader extends Loader
{
    protected ClientInterface $httpClient;
    protected CookieJar $cookieJar;
    protected bool $useCookies = true;

    public function __construct(
        UserAgentInterface $userAgent,
        ?ClientInterface $httpClient = null,
        ?LoggerInterface $logger = null
    ) {
        parent::__construct($userAgent, $logger);

        $this->httpClient = $httpClient ?? new Client();

        $this->onSuccess(function (RequestInterface $request, ResponseInterface $response, LoggerInterface $logger) {
            $logger->info('Loaded ' . $request->getUri()->__toString());
        });

        $this->onError(function (RequestInterface $request, Exception|ResponseInterface $exceptionOrResponse, $logger) {
            $logMessage = 'Failed to load ' . $request->getUri()->__toString() . ': ';

            if ($exceptionOrResponse instanceof ResponseInterface) {
                $logMessage .= 'got response ' . $exceptionOrResponse->getStatusCode() . ' - ' .
                    $exceptionOrResponse->getReasonPhrase();
            } else {
                $logMessage .= $exceptionOrResponse->getMessage();
            }

            $logger->error($logMessage);
        });

        $this->cookieJar = new CookieJar();
    }

    public function load(mixed $subject): ?RespondedRequest
    {
        $request = $this->validateSubjectType($subject);

        if (!$this->isAllowedToBeLoaded($request->getUri())) {
            return null;
        }

        $request = $this->prepareRequest($request);
        $this->callHook('beforeLoad', $request);

        try {
            $respondedRequest = $this->getFromCache($request);
            $isFromCache = $respondedRequest !== null;

            if (!$respondedRequest) {
                $respondedRequest  = $this->handleRedirects($request);
            }

            if ($respondedRequest->response->getStatusCode() < 400) {
                $this->callHook('onSuccess', $request, $respondedRequest->response);
            } else {
                $this->callHook('onError', $request, $respondedRequest->response);
            }

            if (!$isFromCache && $this->cache) {
                $responseCacheItem = HttpResponseCacheItem::fromAggregate($respondedRequest);
                $this->cache->set($responseCacheItem->key(), $responseCacheItem);
            }

            return $respondedRequest;
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
     * @throws LoadingException
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function loadOrFail(mixed $subject): RespondedRequest
    {
        $request = $this->validateSubjectType($subject);
        $this->isAllowedToBeLoaded($request->getUri(), true);
        $request = $this->prepareRequest($request);
        $this->callHook('beforeLoad', $request);
        $respondedRequest = $this->getFromCache($request);
        $isFromCache = $respondedRequest !== null;

        if (!$respondedRequest) {
            $respondedRequest = $this->handleRedirects($request);
        }

        if ($respondedRequest->response->getStatusCode() >= 400) {
            throw new LoadingException('Failed to load ' . $request->getUri()->__toString());
        }

        $this->callHook('onSuccess', $request, $respondedRequest->response);
        $this->callHook('afterLoad', $request);

        if (!$isFromCache && $this->cache) {
            $responseCacheItem = HttpResponseCacheItem::fromAggregate($respondedRequest);
            $this->cache->set($responseCacheItem->key(), $responseCacheItem);
        }

        return $respondedRequest;
    }

    public function dontUseCookies(): static
    {
        $this->useCookies = false;

        return $this;
    }

    public function flushCookies(): void
    {
        $this->cookieJar->flush();
    }

    /**
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    protected function getFromCache(RequestInterface $request): ?RespondedRequest
    {
        if (!$this->cache) {
            return null;
        }

        $key = HttpResponseCacheItem::keyFromRequest($request);

        if ($this->cache->has($key)) {
            $this->logger->info('Found ' . $request->getUri()->__toString() . ' in cache.');
            $responseCacheItem = $this->cache->get($key);

            return $responseCacheItem->aggregate();
        }

        return null;
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

    protected function prepareRequest(RequestInterface $request): RequestInterface
    {
        $request = $request->withHeader('User-Agent', $this->userAgent->__toString());
        $request = $this->addCookiesToRequest($request);

        return $request;
    }

    /**
     * @throws ClientExceptionInterface
     */
    private function handleRedirects(
        RequestInterface  $request,
        ?RespondedRequest $aggregate = null
    ): RespondedRequest {
        $this->trackRequestStart();
        $response = $this->httpClient->sendRequest($request);
        $this->trackRequestEnd();

        if (!$aggregate) {
            $aggregate = new RespondedRequest($request, $response);
        } else {
            $aggregate->setResponse($response);
        }

        $this->addCookiesToJar($aggregate);

        if ($aggregate->isRedirect()) {
            $this->logger()->info('Load redirect to: ' . $aggregate->effectiveUri());
            $newRequest = $request->withUri(Url::parsePsr7($aggregate->effectiveUri()));

            return $this->handleRedirects($newRequest, $aggregate);
        }

        return $aggregate;
    }

    private function addCookiesToJar(RespondedRequest $aggregate): void
    {
        if ($this->useCookies) {
            try {
                $this->cookieJar->addFrom($aggregate->effectiveUri(), $aggregate->response);
            } catch (Exception $exception) {
                $this->logger->warning('Problem when adding cookies to the Jar: ' . $exception->getMessage());
            }
        }
    }

    private function addCookiesToRequest(RequestInterface $request): RequestInterface
    {
        if (!$this->useCookies) {
            return $request;
        }

        foreach ($this->cookieJar->getFor($request->getUri()) as $cookie) {
            $request = $request->withAddedHeader('Cookie', $cookie->__toString());
        }

        return $request;
    }
}
