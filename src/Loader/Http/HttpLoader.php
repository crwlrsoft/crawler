<?php

namespace Crwlr\Crawler\Loader\Http;

use Crwlr\Crawler\Loader\Http\Exceptions\LoadingException;
use Crwlr\Crawler\Loader\Http\Messages\RespondedRequest;
use Crwlr\Crawler\Loader\Http\Politeness\RetryErrorResponseHandler;
use Crwlr\Crawler\Loader\Http\Politeness\Throttler;
use Crwlr\Crawler\Steps\Filters\FilterInterface;
use Crwlr\Crawler\UserAgents\UserAgentInterface;
use Crwlr\Url\Url;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Psr7\Response;
use HeadlessChromium\Browser;
use HeadlessChromium\Exception\CommunicationException;
use HeadlessChromium\Exception\NavigationExpired;
use HeadlessChromium\Exception\NoResponseAvailable;
use HeadlessChromium\Exception\OperationTimedOut;
use InvalidArgumentException;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Throwable;

class HttpLoader extends HttpBaseLoader
{
    protected ClientInterface $httpClient;

    protected ?HeadlessBrowserLoaderHelper $browserHelper = null;

    protected bool $useHeadlessBrowser = false;

    /**
     * @var mixed[]
     */
    protected array $defaultGuzzleClientConfig = [
        'connect_timeout' => 10,
        'timeout' => 60,
    ];

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
        RetryErrorResponseHandler $retryErrorResponseHandler = new RetryErrorResponseHandler(),
        array $defaultGuzzleClientConfig = [],
    ) {
        parent::__construct($userAgent, $logger, $throttler, $retryErrorResponseHandler);

        $this->httpClient = $httpClient ?? new Client($this->mergeClientConfigWithDefaults($defaultGuzzleClientConfig));


    }

    /**
     * @param mixed $subject
     * @return RespondedRequest|null
     */
    public function load(mixed $subject): ?RespondedRequest
    {
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

            $request = $this->prepareRequest($request);

            $this->callHook('beforeLoad', $request);

            $respondedRequest = $this->getFromCache($request);

            $isFromCache = $respondedRequest !== null;

            if ($isFromCache) {
                $this->callHook('onCacheHit', $request, $respondedRequest->response);
            }

            if (!$respondedRequest) {
                $respondedRequest = $this->waitForGoAndLoadViaClientOrHeadlessBrowser($request);
            }

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

    public function loadOrFail(mixed $subject): RespondedRequest
    {
        $request = $this->validateSubjectType($subject);

        try {
            $this->isAllowedToBeLoaded($request->getUri(), true);

            $request = $this->prepareRequest($request);

            $this->callHook('beforeLoad', $request);

            $respondedRequest = $this->getFromCache($request);

            $isFromCache = $respondedRequest !== null;

            if ($isFromCache) {
                $this->callHook('onCacheHit', $request, $respondedRequest->response);
            }

            if (!$respondedRequest) {
                $respondedRequest = $this->waitForGoAndLoadViaClientOrHeadlessBrowser($request);
            }

            if ($respondedRequest->response->getStatusCode() >= 400) {
                throw new LoadingException('Failed to load ' . $request->getUri()->__toString());
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

    public function useHeadlessBrowser(): static
    {
        $this->useHeadlessBrowser = true;

        return $this;
    }

    public function useHttpClient(): static
    {
        $this->useHeadlessBrowser = false;

        $this->browserHelper()->closeBrowser();

        return $this;
    }

    public function usesHeadlessBrowser(): bool
    {
        return $this->useHeadlessBrowser;
    }

    /**
     * @param array<string, mixed> $options
     */
    public function setHeadlessBrowserOptions(array $options): static
    {
        $this->browserHelper()->setOptions($options);

        return $this;
    }

    /**
     * @param array<string, mixed> $options
     */
    public function addHeadlessBrowserOptions(array $options): static
    {
        $this->browserHelper()->addOptions($options);

        return $this;
    }

    public function setChromeExecutable(string $executable): static
    {
        $this->browserHelper()->setExecutable($executable);

        return $this;
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
                'browser for loading.'
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
     * @throws ClientExceptionInterface
     * @throws CommunicationException
     * @throws CommunicationException\CannotReadResponse
     * @throws CommunicationException\InvalidResponse
     * @throws CommunicationException\ResponseHasError
     * @throws LoadingException
     * @throws NavigationExpired
     * @throws NoResponseAvailable
     * @throws OperationTimedOut
     * @throws GuzzleException
     * @throws Exception
     */
    protected function waitForGoAndLoadViaClientOrHeadlessBrowser(RequestInterface $request): RespondedRequest
    {
        $this->throttler->waitForGo($request->getUri());

        $respondedRequest = $this->loadViaClientOrHeadlessBrowser($request);

        if ($this->retryErrorResponseHandler->shouldWait($respondedRequest)) {
            $respondedRequest = $this->retryErrorResponseHandler->handleRetries(
                $respondedRequest,
                (function () use ($request) {
                    $request = $this->prepareRequest($request);

                    return $this->loadViaClientOrHeadlessBrowser($request);
                })->bindTo($this),
            );
        }

        return $respondedRequest;
    }

    /**
     * @throws ClientExceptionInterface
     * @throws CommunicationException
     * @throws CommunicationException\CannotReadResponse
     * @throws CommunicationException\InvalidResponse
     * @throws CommunicationException\ResponseHasError
     * @throws GuzzleException
     * @throws LoadingException
     * @throws NavigationExpired
     * @throws NoResponseAvailable
     * @throws OperationTimedOut
     * @throws Exception
     */
    protected function loadViaClientOrHeadlessBrowser(RequestInterface $request): RespondedRequest
    {
        if ($this->useHeadlessBrowser) {
            return $this->loadViaHeadlessBrowser($request);
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
     * @throws CommunicationException
     * @throws CommunicationException\CannotReadResponse
     * @throws CommunicationException\InvalidResponse
     * @throws CommunicationException\ResponseHasError
     * @throws NavigationExpired
     * @throws NoResponseAvailable
     * @throws OperationTimedOut
     * @throws Exception
     */
    protected function loadViaHeadlessBrowser(RequestInterface $request): RespondedRequest
    {
        $browser = $this->getBrowser($request);

        $page = $browser->createPage();

        $statusCode = 200;

        $responseHeaders = [];

        $page->getSession()->once(
            "method:Network.responseReceived",
            function ($params) use (&$statusCode, &$responseHeaders) {
                $statusCode = $params['response']['status'];

                $responseHeaders = $this->browserHelper()->sanitizeResponseHeaders($params['response']['headers']);
            }
        );

        $this->throttler->trackRequestStartFor($request->getUri());

        $page
            ->navigate($request->getUri()->__toString())
            ->waitForNavigation();

        $this->throttler->trackRequestEndFor($request->getUri());

        $html = $page->getHtml();

        return new RespondedRequest(
            $request,
            new Response($statusCode, $responseHeaders, $html)
        );
    }

    protected function browserHelper(): HeadlessBrowserLoaderHelper
    {
        if (!$this->browserHelper) {
            $this->browserHelper = new HeadlessBrowserLoaderHelper();
        }

        return $this->browserHelper;
    }

    /**
     * @throws Exception
     */
    protected function getBrowser(RequestInterface $request): Browser
    {
        if (!empty($this->proxies)) {
            return $this->browserHelper()->getBrowser($request, $this->proxies->getProxy());
        }

        return $this->browserHelper()->getBrowser($request);
    }
}
