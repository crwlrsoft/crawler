<?php

namespace Crwlr\Crawler\Loader;

use Crwlr\Crawler\Aggregates\RequestResponseAggregate;
use Crwlr\Crawler\Cache\HttpResponseCacheItem;
use Crwlr\Crawler\Logger\CliLogger;
use Crwlr\Crawler\UserAgent;
use Crwlr\Url\Exceptions\InvalidUrlException;
use Crwlr\Url\Url;
use GuzzleHttp\Client;
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

    public function __construct(
        UserAgent $userAgent,
        ?ClientInterface $httpClient = null,
        ?LoggerInterface $logger = null
    ) {
        parent::__construct($userAgent, $logger ?? new CliLogger());

        $this->httpClient = $httpClient ?? new Client();

        $this->onSuccess(function (RequestInterface $request, ResponseInterface $response, LoggerInterface $logger) {
            $logger->info('Loaded ' . $request->getUri()->__toString());
        });

        $this->onError(function (RequestInterface $request, $exception, $logger) {
            $logger->error(
                'Failed to load ' . $request->getUri()->__toString() . ': ' . $exception->getMessage()
            );
        });
    }

    public function load(mixed $subject): ?RequestResponseAggregate
    {
        $request = $this->validateSubjectType($subject);

        if (!$this->isAllowedToBeLoaded($request->getUri())) {
            return null;
        }

        $request = $request->withHeader('User-Agent', $this->userAgent->__toString());
        $this->callHook('beforeLoad', $request);

        try {
            if ($this->cache) {
                $key = HttpResponseCacheItem::keyFromRequest($request);

                if ($this->cache->has($key)) {
                    $this->logger->info('Found ' . $request->getUri() . ' in cache.');
                    $responseCacheItem = $this->cache->get($key);

                    return $responseCacheItem->aggregate();
                }
            }

            $requestResponseAggregate = $this->handleRedirects($request);
            $this->callHook('onSuccess', $request, $requestResponseAggregate->response);

            if ($this->cache) {
                $responseCacheItem = HttpResponseCacheItem::fromAggregate($requestResponseAggregate);
                $this->cache->set($responseCacheItem->key(), $responseCacheItem);
            }

            return $requestResponseAggregate;
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
    public function loadOrFail(mixed $subject): RequestResponseAggregate
    {
        $request = $this->validateSubjectType($subject);
        $this->isAllowedToBeLoaded($request->getUri(), true);
        $request = $request->withHeader('User-Agent', $this->userAgent->__toString());
        $requestResponseAggregate = $this->handleRedirects($request);
        $this->callHook('onSuccess', $request, $requestResponseAggregate);
        $this->callHook('afterLoad', $request);

        return $requestResponseAggregate;
    }

    /**
     * @throws ClientExceptionInterface
     */
    private function handleRedirects(
        RequestInterface $request,
        ?RequestResponseAggregate $aggregate = null
    ): RequestResponseAggregate {
        $this->trackRequestStart();
        $response = $this->httpClient->sendRequest($request);
        $this->trackRequestEnd();

        if (!$aggregate) {
            $aggregate = new RequestResponseAggregate($request, $response);
        } else {
            $aggregate->setResponse($response);
        }

        if ($aggregate->isRedirect()) {
            $this->logger()->info('Load redirect to: ' . $aggregate->effectiveUri());
            $newRequest = $request->withUri(Url::parsePsr7($aggregate->effectiveUri()));

            return $this->handleRedirects($newRequest, $aggregate);
        }

        return $aggregate;
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
