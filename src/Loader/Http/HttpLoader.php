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
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;

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
        return $this->handleLoad($subject, function (RequestInterface $request) {
            if ($this->useHeadlessBrowser) {
                $proxy = $this->proxies?->getProxy() ?? null;

                return $this->browserHelper()->navigateToPageAndGetRespondedRequest($request, $this->throttler, $proxy);
            }

            return $this->handleRedirects($request);
        });
    }

    public function loadOrFail(mixed $subject): RespondedRequest
    {
        return $this->handleLoadOrFail($subject, function (RequestInterface $request) {
            if ($this->useHeadlessBrowser) {
                $proxy = $this->proxies?->getProxy() ?? null;

                return $this->browserHelper()->navigateToPageAndGetRespondedRequest($request, $this->throttler, $proxy);
            }

            return $this->handleRedirects($request);
        });
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

    protected function browserHelper(): HeadlessBrowserLoaderHelper
    {
        if (!$this->browserHelper) {
            $this->browserHelper = new HeadlessBrowserLoaderHelper();
        }

        return $this->browserHelper;
    }
}
