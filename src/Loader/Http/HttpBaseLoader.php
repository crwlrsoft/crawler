<?php

namespace Crwlr\Crawler\Loader\Http;

use Crwlr\Crawler\Loader\Http\Cookies\CookieJar;
use Crwlr\Crawler\Loader\Http\Exceptions\LoadingException;
use Crwlr\Crawler\Loader\Http\Messages\RespondedRequest;
use Crwlr\Crawler\Loader\Http\Politeness\RetryErrorResponseHandler;
use Crwlr\Crawler\Loader\Http\Politeness\RobotsTxtHandler;
use Crwlr\Crawler\Loader\Http\Politeness\Throttler;
use Crwlr\Crawler\Loader\Loader;
use Crwlr\Crawler\Steps\Filters\FilterInterface;
use Crwlr\Crawler\UserAgents\UserAgentInterface;
use Crwlr\Crawler\Utils\RequestKey;
use Crwlr\Url\Exceptions\InvalidUrlException;
use Crwlr\Url\Url;
use Error;
use Exception;
use GuzzleHttp\Psr7\Request;
use InvalidArgumentException;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;
use Psr\Log\LoggerInterface;

abstract class HttpBaseLoader extends Loader
{
    protected CookieJar $cookieJar;

    protected bool $useCookies = true;

    protected RobotsTxtHandler $robotsTxtHandler;

    protected Throttler $throttler;

    protected int $maxRedirects = 10;

    protected bool $retryCachedErrorResponses = false;

    protected bool $writeOnlyCache = false;

    /**
     * @var array<int, FilterInterface>
     */
    protected array $cacheUrlFilters = [];

    protected ?ProxyManager $proxies = null;

    public function __construct(
        UserAgentInterface $userAgent,
        ?LoggerInterface $logger = null,
        ?Throttler $throttler = null,
        protected RetryErrorResponseHandler $retryErrorResponseHandler = new RetryErrorResponseHandler(),
    ) {
        parent::__construct($userAgent, $logger);

        $this->retryErrorResponseHandler->setLogger($this->logger);

        $this->onSuccess(function (RequestInterface $request, ResponseInterface $response, LoggerInterface $logger) {
            $logger->info('Loaded ' . $request->getUri()->__toString());
        });

        $this->onError(function (RequestInterface $request, Exception|Error|ResponseInterface $exceptionOrResponse, $logger) {
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

        $this->robotsTxtHandler = new RobotsTxtHandler($this, $this->logger);

        $this->throttler = $throttler ?? new Throttler();
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

    public function setMaxRedirects(int $maxRedirects): static
    {
        $this->maxRedirects = $maxRedirects;

        return $this;
    }

    public function robotsTxt(): RobotsTxtHandler
    {
        return $this->robotsTxtHandler;
    }

    public function throttle(): Throttler
    {
        return $this->throttler;
    }

    public function retryCachedErrorResponses(): static
    {
        $this->retryCachedErrorResponses = true;

        return $this;
    }

    public function writeOnlyCache(): static
    {
        $this->writeOnlyCache = true;

        return $this;
    }

    public function cacheOnlyWhereUrl(FilterInterface $filter): static
    {
        $this->cacheUrlFilters[] = $filter;

        return $this;
    }

    /**
     * @throws Exception
     */
    public function useProxy(string $proxyUrl): void
    {
        $this->checkIfProxiesCanBeUsed();

        $this->proxies = new ProxyManager([$proxyUrl]);
    }

    /**
     * @param string[] $proxyUrls
     * @throws Exception
     */
    public function useRotatingProxies(array $proxyUrls): void
    {
        $this->checkIfProxiesCanBeUsed();

        $this->proxies = new ProxyManager($proxyUrls);
    }

    /**
     * Optionally implement in child class and throw an Exception when proxies can't be used.
     *
     * @throws Exception
     */
    protected function checkIfProxiesCanBeUsed(): void
    {
        return;
    }

    /**
     * @throws LoadingException
     * @throws Exception
     */
    protected function isAllowedToBeLoaded(UriInterface $uri, bool $throwsException = false): bool
    {
        if (!$this->robotsTxtHandler->isAllowed($uri)) {
            $message = 'Crawler is not allowed to load ' . $uri . ' according to robots.txt file.';

            $this->logger->warning($message);

            if ($throwsException) {
                throw new LoadingException($message);
            }

            return false;
        }

        return true;
    }

    /**
     * @throws \Psr\SimpleCache\InvalidArgumentException
     * @throws Exception
     */
    protected function getFromCache(RequestInterface $request): ?RespondedRequest
    {
        if (!$this->cache || $this->writeOnlyCache) {
            return null;
        }

        $key = RequestKey::from($request);

        if ($this->cache->has($key)) {
            $this->logger->info('Found ' . $request->getUri()->__toString() . ' in cache.');

            $respondedRequest = $this->cache->get($key);

            // Previously, until v0.7 just used serialized arrays. Leave this for backwards compatibility.
            if (is_array($respondedRequest)) {
                $respondedRequest = RespondedRequest::fromArray($respondedRequest);
            }

            if ($this->retryCachedErrorResponses && $respondedRequest->response->getStatusCode() >= 400) {
                $this->logger->info('Cached response was an error response, retry.');

                return null;
            }

            return $respondedRequest;
        }

        return null;
    }

    /**
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    protected function addToCache(RespondedRequest $respondedRequest): void
    {
        if ($this->cache && $this->shouldResponseBeCached($respondedRequest)) {
            $this->cache->set($respondedRequest->cacheKey(), $respondedRequest);
        }
    }

    protected function shouldResponseBeCached(RespondedRequest $respondedRequest): bool
    {
        if (!empty($this->cacheUrlFilters)) {
            foreach ($this->cacheUrlFilters as $filter) {
                foreach ($respondedRequest->allUris() as $url) {
                    if (!$filter->evaluate($url)) {
                        return false;
                    }
                }
            }
        }

        return true;
    }

    /**
     * @throws InvalidArgumentException
     */
    protected function validateSubjectType(RequestInterface|string $requestOrUri): RequestInterface
    {
        if (is_string($requestOrUri)) {
            try {
                return new Request('GET', Url::parsePsr7($requestOrUri));
            } catch (InvalidUrlException) {
                throw new InvalidArgumentException('Invalid URL.');
            }
        }

        return $requestOrUri;
    }

    /**
     * @throws Exception
     */
    protected function prepareRequest(RequestInterface $request): RequestInterface
    {
        $request = $request->withHeader('User-Agent', $this->userAgent->__toString());

        // When writing tests I found that guzzle somehow messed up headers with multiple strings as value in the PSR-7
        // request object. It sent only the last part of the array, instead of concatenating the array of strings to a
        // comma separated string. Don't know if that happens with all handlers (curl, stream), will investigate
        // further. But until this is fixed, we just prepare the headers ourselves.
        foreach ($request->getHeaders() as $headerName => $headerValues) {
            $request = $request->withHeader($headerName, $request->getHeaderLine($headerName));
        }

        return $this->addCookiesToRequest($request);
    }

    protected function addCookiesToJar(RespondedRequest $respondedRequest): void
    {
        if ($this->useCookies) {
            try {
                $this->cookieJar->addFrom($respondedRequest->effectiveUri(), $respondedRequest->response);
            } catch (Exception $exception) {
                $this->logger->warning('Problem when adding cookies to the Jar: ' . $exception->getMessage());
            }
        }
    }

    /**
     * @throws Exception
     */
    protected function addCookiesToRequest(RequestInterface $request): RequestInterface
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
