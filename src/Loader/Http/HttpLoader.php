<?php

namespace Crwlr\Crawler\Loader\Http;

use Crwlr\Crawler\Loader\Http\Cache\RetryManager;
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
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Psr7\Request;
use HeadlessChromium\Exception\CommunicationException;
use HeadlessChromium\Exception\CommunicationException\CannotReadResponse;
use HeadlessChromium\Exception\CommunicationException\InvalidResponse;
use HeadlessChromium\Exception\CommunicationException\ResponseHasError;
use HeadlessChromium\Exception\JavascriptException;
use HeadlessChromium\Exception\NavigationExpired;
use HeadlessChromium\Exception\NoResponseAvailable;
use HeadlessChromium\Exception\OperationTimedOut;
use InvalidArgumentException;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;
use Psr\Log\LoggerInterface;
use Throwable;

class HttpLoader extends Loader
{
    protected ClientInterface $httpClient;

    protected CookieJar $cookieJar;

    protected bool $useCookies = true;

    protected ?HeadlessBrowserLoaderHelper $browserHelper = null;

    protected bool $useHeadlessBrowser = false;

    protected ?RobotsTxtHandler $robotsTxtHandler = null;

    protected Throttler $throttler;

    /**
     * @var mixed[]
     */
    protected array $defaultGuzzleClientConfig = [
        'connect_timeout' => 10,
        'timeout' => 60,
    ];

    protected int $maxRedirects = 10;

    protected ?RetryManager $retryCachedErrorResponses = null;

    protected bool $writeOnlyCache = false;

    /**
     * @var array<int, FilterInterface>
     */
    protected array $cacheUrlFilters = [];

    protected ?ProxyManager $proxies = null;

    /**
     * @param mixed[] $defaultGuzzleClientConfig
     */
    public function __construct(
        UserAgentInterface $userAgent,
        ?ClientInterface $httpClient = null,
        ?LoggerInterface $logger = null,
        ?Throttler $throttler = null,
        protected RetryErrorResponseHandler $retryErrorResponseHandler = new RetryErrorResponseHandler(),
        array $defaultGuzzleClientConfig = [],
    ) {
        parent::__construct($userAgent, $logger);

        $this->retryErrorResponseHandler->setLogger($this->logger);

        $this->httpClient = $httpClient ?? new Client($this->mergeClientConfigWithDefaults($defaultGuzzleClientConfig));

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

        $this->throttler = $throttler ?? new Throttler();
    }

    /**
     * @param mixed $subject
     * @return RespondedRequest|null
     */
    public function load(mixed $subject): ?RespondedRequest
    {
        $this->_resetCalledHooks();

        try {
            $request = $this->validateSubjectType($subject);
        } catch (InvalidArgumentException) {
            $this->logger->error('Invalid input URL: ' . var_export($subject, true));

            return null;
        }

        try {
            if (!$this->isAllowedToBeLoaded($request->getUri())) {
                return null;
            }

            $isFromCache = false;

            $respondedRequest = $this->tryLoading($request, $isFromCache);

            if ($respondedRequest->response->getStatusCode() < 400) {
                $this->callHook('onSuccess', $request, $respondedRequest->response);
            } else {
                $this->callHook('onError', $request, $respondedRequest->response);
            }

            if (!$isFromCache) {
                $this->addToCache($respondedRequest);
            }

            return $respondedRequest;
        } catch (Throwable $exception) {
            // Don't move to finally so hooks don't run before it.
            $this->throttler->trackRequestEndFor($request->getUri());

            $this->callHook('onError', $request, $exception);

            return null;
        } finally {
            $this->callHook('afterLoad', $request);
        }
    }

    /**
     * @throws LoadingException
     */
    public function loadOrFail(mixed $subject): RespondedRequest
    {
        $request = $this->validateSubjectType($subject);

        try {
            $this->isAllowedToBeLoaded($request->getUri(), true);

            $isFromCache = false;

            $respondedRequest = $this->tryLoading($request, $isFromCache);

            if ($respondedRequest->response->getStatusCode() >= 400) {
                throw LoadingException::make($request->getUri(), $respondedRequest->response->getStatusCode());
            }

            $this->callHook('onSuccess', $request, $respondedRequest->response);

            $this->callHook('afterLoad', $request);

            if (!$isFromCache) {
                $this->addToCache($respondedRequest);
            }

            return $respondedRequest;
        } catch (Throwable $exception) {
            throw LoadingException::from($exception);
        }
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

    public function useHeadlessBrowser(): static
    {
        $this->useHeadlessBrowser = true;

        return $this;
    }

    /**
     * @throws Exception
     */
    public function useHttpClient(): static
    {
        $this->useHeadlessBrowser = false;

        $this->browser()->closeBrowser();

        return $this;
    }

    public function usesHeadlessBrowser(): bool
    {
        return $this->useHeadlessBrowser;
    }

    public function setMaxRedirects(int $maxRedirects): static
    {
        $this->maxRedirects = $maxRedirects;

        return $this;
    }

    public function robotsTxt(): RobotsTxtHandler
    {
        if (!$this->robotsTxtHandler) {
            $this->robotsTxtHandler = new RobotsTxtHandler($this, $this->logger);
        }

        return $this->robotsTxtHandler;
    }

    public function throttle(): Throttler
    {
        return $this->throttler;
    }

    public function retryCachedErrorResponses(): RetryManager
    {
        $this->retryCachedErrorResponses = new RetryManager();

        return $this->retryCachedErrorResponses;
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

    public function browser(): HeadlessBrowserLoaderHelper
    {
        if (!$this->browserHelper) {
            $this->browserHelper = new HeadlessBrowserLoaderHelper();
        }

        return $this->browserHelper;
    }

    /**
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function addToCache(RespondedRequest $respondedRequest): void
    {
        if ($this->cache && $this->shouldResponseBeCached($respondedRequest)) {
            $this->cache->set($respondedRequest->cacheKey(), $respondedRequest);
        }
    }

    /**
     * @throws LoadingException|Throwable|\Psr\SimpleCache\InvalidArgumentException
     */
    protected function tryLoading(
        RequestInterface $request,
        bool &$isFromCache,
    ): RespondedRequest {
        $request = $this->prepareRequest($request);

        $this->callHook('beforeLoad', $request);

        $respondedRequest = $this->shouldRequestBeServedFromCache($request) ? $this->getFromCache($request) : null;

        $isFromCache = $respondedRequest !== null;

        if ($isFromCache) {
            $this->callHook('onCacheHit', $request, $respondedRequest->response);
        }

        if (!$respondedRequest) {
            $respondedRequest = $this->waitForGoAndLoad($request);
        }

        return $respondedRequest;
    }

    /**
     * @throws ClientExceptionInterface
     * @throws GuzzleException
     * @throws LoadingException
     * @throws CommunicationException
     * @throws CannotReadResponse
     * @throws InvalidResponse
     * @throws ResponseHasError
     * @throws JavascriptException
     * @throws NavigationExpired
     * @throws NoResponseAvailable
     * @throws OperationTimedOut
     * @throws Exception
     */
    protected function waitForGoAndLoad(RequestInterface $request): RespondedRequest
    {
        $this->throttler->waitForGo($request->getUri());

        $respondedRequest = $this->loadViaClientOrHeadlessBrowser($request);

        if ($this->retryErrorResponseHandler->shouldWait($respondedRequest)) {
            $respondedRequest = $this->retryErrorResponseHandler->handleRetries(
                $respondedRequest,
                function () use ($request) {
                    $request = $this->prepareRequest($request);

                    return $this->loadViaClientOrHeadlessBrowser($request);
                },
            );
        }

        return $respondedRequest;
    }

    /**
     * @throws ClientExceptionInterface
     * @throws GuzzleException
     * @throws LoadingException
     * @throws CommunicationException
     * @throws CannotReadResponse
     * @throws InvalidResponse
     * @throws ResponseHasError
     * @throws JavascriptException
     * @throws NavigationExpired
     * @throws NoResponseAvailable
     * @throws OperationTimedOut
     */
    protected function loadViaClientOrHeadlessBrowser(RequestInterface $request): RespondedRequest
    {
        if ($this->useHeadlessBrowser) {
            $proxy = $this->proxies?->getProxy() ?? null;

            return $this->browser()->navigateToPageAndGetRespondedRequest(
                $request,
                $this->throttler,
                $proxy,
                $this->cookieJar,
            );
        }

        return $this->handleRedirects($request);
    }

    /**
     * @throws ClientExceptionInterface
     * @throws LoadingException
     * @throws GuzzleException
     * @throws Exception
     */
    protected function handleRedirects(
        RequestInterface  $request,
        ?RespondedRequest $respondedRequest = null,
        int $redirectNumber = 0,
    ): RespondedRequest {
        if ($redirectNumber >= $this->maxRedirects) {
            throw new LoadingException('Too many redirects.');
        }

        if (!$respondedRequest) {
            $this->throttler->trackRequestStartFor($request->getUri());
        }

        if ($this->proxies && $this->httpClient instanceof Client) {
            $response = $this->sendProxiedRequestUsingGuzzle($request, $this->httpClient);
        } else {
            $response = $this->httpClient->sendRequest($request);
        }

        if (!$respondedRequest) {
            $respondedRequest = new RespondedRequest($request, $response);
        } else {
            $respondedRequest->setResponse($response);
        }

        $this->addCookiesToJar($respondedRequest);

        if ($respondedRequest->isRedirect()) {
            $this->logger()->info('Load redirect to: ' . $respondedRequest->effectiveUri());

            $newRequest = $request->withUri(Url::parsePsr7($respondedRequest->effectiveUri()));

            $redirectNumber++;

            return $this->handleRedirects($newRequest, $respondedRequest, $redirectNumber);
        } else {
            $this->throttler->trackRequestEndFor($respondedRequest->request->getUri());
        }

        return $respondedRequest;
    }

    /**
     * @throws GuzzleException
     */
    protected function sendProxiedRequestUsingGuzzle(RequestInterface $request, Client $client): ResponseInterface
    {
        return $client->request(
            $request->getMethod(),
            $request->getUri(),
            [
                'headers' => $request->getHeaders(),
                'proxy' => $this->proxies?->getProxy(),
                'version' => $request->getProtocolVersion(),
                'body' => $request->getBody(),
            ],
        );
    }

    /**
     * @return void
     * @throws Exception
     */
    protected function checkIfProxiesCanBeUsed(): void
    {
        if (!$this->usesHeadlessBrowser() && !$this->httpClient instanceof Client) {
            throw new Exception(
                'The included proxy feature can only be used when using a guzzle HTTP client or headless chrome ' .
                'browser for loading.',
            );
        }
    }

    /**
     * @param mixed[] $config
     * @return mixed[]
     */
    protected function mergeClientConfigWithDefaults(array $config): array
    {
        $merged = $this->defaultGuzzleClientConfig;

        foreach ($config as $key => $value) {
            $merged[$key] = $value;
        }

        return $merged;
    }

    /**
     * @throws LoadingException
     * @throws Exception
     */
    protected function isAllowedToBeLoaded(UriInterface $uri, bool $throwsException = false): bool
    {
        if (!$this->robotsTxt()->isAllowed($uri)) {
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

            if ($this->retryCachedErrorResponses?->shallBeRetried($respondedRequest->response->getStatusCode())) {
                $this->logger->info('Cached response was an error response, retry.');

                return null;
            }

            return $respondedRequest;
        }

        return null;
    }

    protected function shouldResponseBeCached(RespondedRequest $respondedRequest): bool
    {
        if (!empty($this->cacheUrlFilters)) {
            foreach ($this->cacheUrlFilters as $filter) {
                $noUrlMatched = true;

                foreach ($respondedRequest->allUris() as $url) {
                    if ($filter->evaluate($url)) {
                        $noUrlMatched = false;
                    }
                }

                if ($noUrlMatched) {
                    return false;
                }
            }
        }

        return true;
    }

    protected function shouldRequestBeServedFromCache(RequestInterface $request): bool
    {
        if (!empty($this->cacheUrlFilters)) {
            foreach ($this->cacheUrlFilters as $filter) {
                if (!$filter->evaluate((string) $request->getUri())) {
                    return false;
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
        if (!$this->useCookies || $this->usesHeadlessBrowser()) {
            return $request;
        }

        foreach ($this->cookieJar->getFor($request->getUri()) as $cookie) {
            $request = $request->withAddedHeader('Cookie', $cookie->__toString());
        }

        return $request;
    }
}
