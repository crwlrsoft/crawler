<?php

namespace Crwlr\Crawler\Loader\Http;

use Closure;
use Crwlr\Crawler\Loader\Http\Browser\Screenshot;
use Crwlr\Crawler\Loader\Http\Cookies\CookieJar;
use Crwlr\Crawler\Loader\Http\Cookies\Exceptions\InvalidCookieException;
use Crwlr\Crawler\Loader\Http\Messages\RespondedRequest;
use Crwlr\Crawler\Loader\Http\Politeness\Throttler;
use Exception;
use GuzzleHttp\Psr7\Response;
use HeadlessChromium\Browser;
use HeadlessChromium\BrowserFactory;
use HeadlessChromium\Communication\Message;
use HeadlessChromium\Exception\CommunicationException;
use HeadlessChromium\Exception\CommunicationException\CannotReadResponse;
use HeadlessChromium\Exception\CommunicationException\InvalidResponse;
use HeadlessChromium\Exception\CommunicationException\ResponseHasError;
use HeadlessChromium\Exception\JavascriptException;
use HeadlessChromium\Exception\NavigationExpired;
use HeadlessChromium\Exception\NoResponseAvailable;
use HeadlessChromium\Exception\OperationTimedOut;
use HeadlessChromium\Exception\TargetDestroyed;
use HeadlessChromium\Page;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\UriInterface;
use Psr\Log\LoggerInterface;
use Throwable;

class HeadlessBrowserLoaderHelper
{
    protected ?string $executable = null;

    /**
     * @var array<string, mixed>
     */
    protected array $options = [
        'windowSize' => [1920, 1000],
    ];

    protected bool $optionsDirty = false;

    protected ?Browser $browser = null;

    protected ?Page $page = null;

    protected ?string $proxy = null;

    protected ?string $waitForEvent = null;

    protected int $timeout = 30_000;

    protected ?string $pageInitScript = null;

    protected bool $useNativeUserAgent = false;

    protected bool $includeShadowElements = false;

    /**
     * @var Closure[]
     */
    protected array $tempPostNavigateHooks = [];

    public function __construct(
        private ?BrowserFactory $browserFactory = null,
        protected ?LoggerInterface $logger = null,
    ) {}

    /**
     * Set temporary post navigate hooks
     *
     * They will be executed after the next call to navigateToPageAndGetRespondedRequest()
     * and forgotten afterward.
     *
     * @param Closure[] $hooks
     */
    public function setTempPostNavigateHooks(array $hooks): static
    {
        $this->tempPostNavigateHooks = $hooks;

        return $this;
    }

    /**
     * @throws OperationTimedOut
     * @throws CommunicationException
     * @throws NoResponseAvailable
     * @throws NavigationExpired
     * @throws InvalidResponse
     * @throws CannotReadResponse
     * @throws ResponseHasError
     * @throws JavascriptException
     * @throws Exception
     */
    public function navigateToPageAndGetRespondedRequest(
        RequestInterface $request,
        Throttler $throttler,
        ?string $proxy = null,
        ?CookieJar $cookieJar = null,
    ): RespondedRequest {
        if (!$this->page || $this->shouldRenewBrowser($proxy)) {
            $this->page = $this->getBrowser($request, $proxy)->createPage();
        } else {
            try {
                $this->page->assertNotClosed();
            } catch (TargetDestroyed) {
                $this->page = $this->getBrowser($request, $proxy)->createPage();
            }
        }

        if ($cookieJar === null) {
            $this->page->getSession()->sendMessageSync(new Message('Network.clearBrowserCookies'));
        }

        $statusCode = 200;

        $responseHeaders = [];

        $requestId = null;

        $this->page->getSession()->once(
            "method:Network.responseReceived",
            function ($params) use (&$statusCode, &$responseHeaders, &$requestId) {
                $statusCode = $params['response']['status'];

                $responseHeaders = $this->sanitizeResponseHeaders($params['response']['headers']);

                $requestId = $params['requestId'] ?? null;
            },
        );

        $throttler->trackRequestStartFor($request->getUri());

        $this->navigate($request->getUri()->__toString());

        $throttler->trackRequestEndFor($request->getUri());

        $hookActionData = $this->callPostNavigateHooks();

        if (is_string($requestId) && $this->page && !$this->responseIsHtmlDocument($this->page)) {
            $html = $this->tryToGetRawResponseBody($this->page, $requestId) ?? $this->getHtmlFromPage();
        } else {
            $html = $this->getHtmlFromPage();
        }

        $this->addCookiesToJar($cookieJar, $request->getUri());

        return new RespondedRequest(
            $request,
            new Response($statusCode, $responseHeaders, $html),
            $hookActionData['screenshots'] ?? [],
        );
    }

    public function getOpenBrowser(): ?Browser
    {
        return $this->browser;
    }

    public function getOpenPage(): ?Page
    {
        return $this->page;
    }

    /**
     * @throws Exception
     */
    public function closeBrowser(): void
    {
        if ($this->browser) {
            if ($this->page) {
                $this->page->close();

                $this->page = null;
            }

            $this->browser->close();

            $this->browser = null;
        }
    }

    public function setExecutable(string $executable): static
    {
        $this->executable = $executable;

        return $this;
    }

    /**
     * @param array<string, mixed> $options
     */
    public function setOptions(array $options): static
    {
        $this->options = $options;

        $this->optionsDirty = true;

        return $this;
    }

    /**
     * @param array<string, mixed> $options
     */
    public function addOptions(array $options): static
    {
        foreach ($options as $key => $value) {
            $this->options[$key] = $value;
        }

        $this->optionsDirty = true;

        return $this;
    }

    public function waitForNavigationEvent(string $eventName): static
    {
        $this->waitForEvent = $eventName;

        return $this;
    }

    public function getTimeout(): int
    {
        return $this->timeout;
    }

    public function setTimeout(int $timeout): static
    {
        $this->timeout = $timeout;

        return $this;
    }

    /**
     * @param string[] $headers
     * @return string[]
     */
    public function sanitizeResponseHeaders(array $headers): array
    {
        foreach ($headers as $key => $value) {
            $headers[$key] = explode(PHP_EOL, $value)[0];
        }

        return $headers;
    }

    /**
     * @param string $scriptSource
     * @return $this
     */
    public function setPageInitScript(string $scriptSource): static
    {
        $this->pageInitScript = $scriptSource;

        return $this;
    }

    public function useNativeUserAgent(): static
    {
        $this->useNativeUserAgent = true;

        return $this;
    }

    public function includeShadowElementsInHtml(): static
    {
        $this->includeShadowElements = true;

        return $this;
    }

    /**
     * @throws OperationTimedOut
     * @throws CommunicationException
     * @throws NavigationExpired
     * @throws NoResponseAvailable
     * @throws InvalidResponse
     * @throws CannotReadResponse
     * @throws ResponseHasError
     */
    protected function navigate(string $url): void
    {
        if ($this->waitForEvent) {
            $this->page?->navigate($url)->waitForNavigation($this->waitForEvent, $this->timeout);
        } else {
            $this->page?->navigate($url)->waitForNavigation(timeout: $this->timeout);
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function callPostNavigateHooks(): array
    {
        $returnData = [];

        if (!empty($this->tempPostNavigateHooks)) {
            foreach ($this->tempPostNavigateHooks as $hook) {
                $returnValue = $hook->call($this, $this->page, $this->logger);

                if ($returnValue instanceof Screenshot) {
                    if (!array_key_exists('screenshots', $returnData)) {
                        $returnData['screenshots'] = [$returnValue];
                    } else {
                        $returnData['screenshots'][] = $returnValue;
                    }
                }
            }
        }

        $this->tempPostNavigateHooks = [];

        return $returnData;
    }

    /**
     * @throws CommunicationException
     * @throws OperationTimedOut
     * @throws NoResponseAvailable
     * @throws InvalidCookieException
     */
    protected function addCookiesToJar(?CookieJar $cookieJar, UriInterface $requestUrl): void
    {
        if (!$cookieJar) {
            return;
        }

        $cookies = $this->page?->getCookies();

        if ($cookies) {
            $cookieJar->addFrom($requestUrl, $cookies);
        }
    }

    /**
     * @throws Exception
     */
    protected function getBrowser(
        RequestInterface $request,
        ?string $proxy = null,
    ): Browser {
        if (!$this->browser || $this->shouldRenewBrowser($proxy)) {
            $this->closeBrowser();

            $options = $this->optionsFromRequest($request, $proxy);

            if (!$this->browserFactory) {
                $this->browserFactory = new BrowserFactory($this->executable);
            }

            $this->browser = $this->browserFactory->createBrowser($options);

            if ($this->pageInitScript) {
                $this->browser->setPagePreScript($this->pageInitScript);
            }

            $this->optionsDirty = false;
        }

        return $this->browser;
    }

    protected function shouldRenewBrowser(?string $proxy): bool
    {
        return $this->optionsDirty || ($proxy !== $this->proxy);
    }

    /**
     * @param RequestInterface $request
     * @return array<string, mixed>
     */
    protected function optionsFromRequest(RequestInterface $request, ?string $proxy = null): array
    {
        $options = $this->options;

        if (isset($request->getHeader('User-Agent')[0]) && !$this->useNativeUserAgent) {
            $options['userAgent'] = $request->getHeader('User-Agent')[0];
        } elseif ($this->useNativeUserAgent && !empty($request->getHeader('User-Agent'))) {
            $request = $request->withoutHeader('User-Agent');
        }

        $options['headers'] = array_merge(
            $options['headers'] ?? [],
            $this->prepareRequestHeaders($request->getHeaders()),
        );

        if (!empty($proxy)) {
            $this->proxy = $options['proxyServer'] = $proxy;
        } else {
            $this->proxy = null;
        }

        return $options;
    }

    /**
     * @param mixed[] $headers
     * @return array<string, string>
     */
    protected function prepareRequestHeaders(array $headers = []): array
    {
        $headers = $this->removeHeadersCausingErrorWithHeadlessBrowser($headers);

        return array_map(function ($headerValue) {
            return is_array($headerValue) ? implode(';', $headerValue) : $headerValue;
        }, $headers);
    }

    /**
     * @param mixed[] $headers
     * @return mixed[]
     */
    protected function removeHeadersCausingErrorWithHeadlessBrowser(array $headers = []): array
    {
        $removeHeaders = ['host'];

        foreach ($headers as $headerName => $headerValue) {
            if (in_array(strtolower($headerName), $removeHeaders, true)) {
                unset($headers[$headerName]);
            }
        }

        return $headers;
    }

    protected function responseIsHtmlDocument(?Page $page = null): bool
    {
        if (!$page) {
            return false;
        }

        try {
            return $page->evaluate(
                <<<JS
                (document.contentType === 'text/html' || document instanceof HTMLDocument) &&
                !(document.contentType === 'text/plain' && document.body.textContent.trimLeft().startsWith('<?xml '))
                JS,
            )->getReturnValue(3000);
        } catch (Throwable $e) {
            return true;
        }
    }

    /**
     * In production, retrieving the raw response body using the Network.getResponseBody message sometimes failed.
     * Waiting briefly before sending the message appeared to resolve the issue.
     * So, this method tries up to three times with a brief wait between each attempt.
     */
    protected function tryToGetRawResponseBody(Page $page, string $requestId): ?string
    {
        for ($i = 1; $i <= 3; $i++) {
            try {
                $message = $page->getSession()->sendMessageSync(new Message('Network.getResponseBody', [
                    'requestId' => $requestId,
                ]));

                if ($message->isSuccessful() && $message->getData()['result']['body']) {
                    return $message->getData()['result']['body'];
                }
            } catch (Throwable) {
            }

            usleep($i * 100000);
        }

        return null;
    }

    /**
     * @throws CommunicationException
     * @throws JavascriptException
     */
    protected function getHtmlFromPage(): string
    {
        if ($this->page instanceof Page && $this->includeShadowElements) {
            try {
                // Found this script on
                // https://stackoverflow.com/questions/69867758/how-can-i-get-all-the-html-in-a-document-or-node-containing-shadowroot-elements
                return $this->page->evaluate(<<<JS
                    function extractHTML(node) {
                        if (!node) return ''
                        if (node.nodeType===3) return node.textContent;
                        if (node.nodeType!==1) return ''

                        let html = ''
                        let outer = node.cloneNode();
                        node = node.shadowRoot || node

                        if (node.children.length) {
                            for (let n of node.childNodes) {
                                if (n.assignedNodes) {
                                    if (n.assignedNodes()[0]) {
                                        html += extractHTML(n.assignedNodes()[0])
                                    } else { html += n.innerHTML }
                                } else { html += extractHTML(n) }
                            }
                        } else { html = node.innerHTML }

                        outer.innerHTML = html

                        return outer.outerHTML
                    }

                    extractHTML(document.documentElement);
                    JS)->getReturnValue();
            } catch (Throwable) {
                return $this->page->getHtml();
            }
        }

        return $this->page?->getHtml() ?? '';
    }
}
