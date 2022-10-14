<?php

namespace Crwlr\Crawler\Loader\Http;

use Crwlr\Crawler\Loader\Http\Cache\HttpResponseCacheItem;
use Crwlr\Crawler\Loader\Http\Cookies\CookieJar;
use Crwlr\Crawler\Loader\Http\Exceptions\LoadingException;
use Crwlr\Crawler\Loader\Http\Messages\RespondedRequest;
use Crwlr\Crawler\Loader\Http\Politeness\RobotsTxtHandler;
use Crwlr\Crawler\Loader\Http\Politeness\Throttler;
use Crwlr\Crawler\Loader\Http\Politeness\TooManyRequestsHandler;
use Crwlr\Crawler\Loader\Loader;
use Crwlr\Crawler\UserAgents\UserAgentInterface;
use Crwlr\Url\Exceptions\InvalidUrlException;
use Crwlr\Url\Url;
use Error;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use HeadlessChromium\Browser;
use HeadlessChromium\BrowserFactory;
use HeadlessChromium\Exception\CommunicationException;
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

    protected bool $useHeadlessBrowser = false;

    /**
     * @var mixed[]
     */
    protected array $headlessBrowserOptions = [
        'windowSize' => [1920, 1000],
    ];

    protected bool $headlessBrowserOptionsDirty = false;

    protected ?Browser $headlessBrowser = null;

    protected RobotsTxtHandler $robotsTxtHandler;

    protected Throttler $throttler;

    public function __construct(
        UserAgentInterface $userAgent,
        ?ClientInterface $httpClient = null,
        ?LoggerInterface $logger = null,
        ?Throttler $throttler = null,
        protected TooManyRequestsHandler $tooManyRequestsHandler = new TooManyRequestsHandler(),
    ) {
        parent::__construct($userAgent, $logger);

        $this->httpClient = $httpClient ?? new Client();

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

        $this->robotsTxtHandler = new RobotsTxtHandler($this);

        $this->throttler = $throttler ?? new Throttler();
    }

    /**
     * @param mixed $subject
     * @return RespondedRequest|null
     * @throws LoadingException
     */
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
                $respondedRequest = $this->waitForGoAndLoadViaClientOrHeadlessBrowser($request);
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
            // Don't move to finally so hooks don't run before it.
            $this->throttler->trackRequestEndFor($request->getUri());

            $this->callHook('onError', $request, $exception);

            return null;
        } finally {
            $this->callHook('afterLoad', $request);
        }
    }

    /**
     * @throws ClientExceptionInterface
     * @throws CommunicationException
     * @throws CommunicationException\CannotReadResponse
     * @throws CommunicationException\InvalidResponse
     * @throws CommunicationException\ResponseHasError
     * @throws LoadingException
     * @throws NavigationExpired
     * @throws NoResponseAvailable
     * @throws OperationTimedOut
     * @throws Throwable
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
            $respondedRequest = $this->waitForGoAndLoadViaClientOrHeadlessBrowser($request);
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

    public function useHeadlessBrowser(): static
    {
        $this->useHeadlessBrowser = true;

        return $this;
    }

    public function usesHeadlessBrowser(): bool
    {
        return $this->useHeadlessBrowser;
    }

    public function useHttpClient(): static
    {
        $this->useHeadlessBrowser = false;

        $this->headlessBrowser = null;

        return $this;
    }

    /**
     * @param mixed[] $options
     */
    public function setHeadlessBrowserOptions(array $options): static
    {
        $this->headlessBrowserOptions = $options;

        $this->headlessBrowserOptionsDirty = true;

        return $this;
    }

    /**
     * @param mixed[] $options
     */
    public function addHeadlessBrowserOptions(array $options): static
    {
        foreach ($options as $key => $value) {
            $this->headlessBrowserOptions[$key] = $value;
        }

        $this->headlessBrowserOptionsDirty = true;

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

    /**
     * @throws Exception
     */
    protected function prepareRequest(RequestInterface $request): RequestInterface
    {
        $request = $request->withHeader('User-Agent', $this->userAgent->__toString());

        return $this->addCookiesToRequest($request);
    }

    /**
     * @return RespondedRequest
     * @throws ClientExceptionInterface
     * @throws CommunicationException
     * @throws CommunicationException\CannotReadResponse
     * @throws CommunicationException\InvalidResponse
     * @throws CommunicationException\ResponseHasError
     * @throws LoadingException
     * @throws NavigationExpired
     * @throws NoResponseAvailable
     * @throws OperationTimedOut
     * @throws Throwable
     */
    private function waitForGoAndLoadViaClientOrHeadlessBrowser(RequestInterface $request): RespondedRequest
    {
        $this->throttler->waitForGo($request->getUri());

        $respondedRequest = $this->loadViaClientOrHeadlessBrowser($request);

        if ($respondedRequest->response->getStatusCode() === 429) {
            $respondedRequest = $this->tooManyRequestsHandler->handleRetries(
                $respondedRequest,
                (function () use ($request) {
                    $request = $this->prepareRequest($request);

                    return $this->loadViaClientOrHeadlessBrowser($request);
                })->bindTo($this),
                $this->logger,
            );
        }

        return $respondedRequest;
    }

    /**
     * @param RequestInterface $request
     * @return RespondedRequest
     * @throws ClientExceptionInterface
     * @throws CommunicationException
     * @throws CommunicationException\CannotReadResponse
     * @throws CommunicationException\InvalidResponse
     * @throws CommunicationException\ResponseHasError
     * @throws NavigationExpired
     * @throws NoResponseAvailable
     * @throws OperationTimedOut
     * @throws Throwable
     */
    private function loadViaClientOrHeadlessBrowser(RequestInterface $request): RespondedRequest
    {
        if ($this->useHeadlessBrowser) {
            return $this->loadViaHeadlessBrowser($request);
        }

        return $this->handleRedirects($request);
    }

    /**
     * @throws ClientExceptionInterface
     */
    private function handleRedirects(
        RequestInterface  $request,
        ?RespondedRequest $respondedRequest = null
    ): RespondedRequest {
        if (!$respondedRequest) {
            $this->throttler->trackRequestStartFor($request->getUri());
        }

        $response = $this->httpClient->sendRequest($request);

        $this->throttler->trackRequestEndFor($request->getUri());

        if (!$respondedRequest) {
            $respondedRequest = new RespondedRequest($request, $response);
        } else {
            $respondedRequest->setResponse($response);
        }

        $this->addCookiesToJar($respondedRequest);

        if ($respondedRequest->isRedirect()) {
            $this->logger()->info('Load redirect to: ' . $respondedRequest->effectiveUri());

            $newRequest = $request->withUri(Url::parsePsr7($respondedRequest->effectiveUri()));

            return $this->handleRedirects($newRequest, $respondedRequest);
        }

        return $respondedRequest;
    }

    /**
     * @param RequestInterface $request
     * @return RespondedRequest
     * @throws CommunicationException
     * @throws CommunicationException\CannotReadResponse
     * @throws CommunicationException\InvalidResponse
     * @throws CommunicationException\ResponseHasError
     * @throws NavigationExpired
     * @throws NoResponseAvailable
     * @throws OperationTimedOut
     * @throws Throwable
     */
    private function loadViaHeadlessBrowser(RequestInterface $request): RespondedRequest
    {
        $browser = $this->getBrowser($request);

        $page = $browser->createPage();

        $statusCode = 500;

        $responseHeaders = [];

        $page->getSession()->once(
            "method:Network.responseReceived",
            function ($params) use (& $statusCode, & $responseHeaders) {
                $statusCode = $params['response']['status'];

                $responseHeaders = $this->sanitizeResponseHeaders($params['response']['headers']);
            }
        );

        $page->navigate($request->getUri()->__toString())
            ->waitForNavigation();

        $html = $page->getHtml();

        return new RespondedRequest(
            $request,
            new Response($statusCode, $responseHeaders, $html)
        );
    }

    /**
     * @throws Exception
     */
    private function getBrowser(RequestInterface $request): Browser
    {
        if (!$this->headlessBrowser || $this->headlessBrowserOptionsDirty) {
            $this->headlessBrowser?->close();

            $options = $this->headlessBrowserOptions;

            $options['userAgent'] = $this->userAgent->__toString();

            $options['headers'] = array_merge(
                $options['headers'] ?? [],
                $this->prepareRequestHeadersForHeadlessBrowser($request->getHeaders()),
            );

            $this->headlessBrowser = (new BrowserFactory())->createBrowser($options);

            $this->headlessBrowserOptionsDirty = false;
        }

        return $this->headlessBrowser;
    }

    private function addCookiesToJar(RespondedRequest $respondedRequest): void
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

    /**
     * @param string[] $headers
     * @return string[]
     */
    private function sanitizeResponseHeaders(array $headers): array
    {
        foreach ($headers as $key => $value) {
            $headers[$key] = explode(PHP_EOL, $value)[0];
        }

        return $headers;
    }

    /**
     * @param mixed[] $headers
     * @return array<string, string>
     */
    private function prepareRequestHeadersForHeadlessBrowser(array $headers = []): array
    {
        $headers = $this->removeHeadersCausingErrorWithHeadlessBrowser($headers);

        return array_map(function ($headerValue) {
            return is_array($headerValue) ? reset($headerValue) : $headerValue;
        }, $headers);
    }

    /**
     * @param mixed[] $headers
     * @return mixed[]
     */
    private function removeHeadersCausingErrorWithHeadlessBrowser(array $headers = []): array
    {
        $removeHeaders = ['host'];

        foreach ($headers as $headerName => $headerValue) {
            if (in_array(strtolower($headerName), $removeHeaders, true)) {
                unset($headers[$headerName]);
            }
        }

        return $headers;
    }
}
