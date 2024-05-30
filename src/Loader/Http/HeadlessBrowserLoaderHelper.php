<?php

namespace Crwlr\Crawler\Loader\Http;

use Crwlr\Crawler\Loader\Http\Messages\RespondedRequest;
use Crwlr\Crawler\Loader\Http\Politeness\Throttler;
use Exception;
use GuzzleHttp\Psr7\Response;
use HeadlessChromium\Browser;
use HeadlessChromium\BrowserFactory;
use HeadlessChromium\Exception\CommunicationException;
use HeadlessChromium\Exception\CommunicationException\CannotReadResponse;
use HeadlessChromium\Exception\CommunicationException\InvalidResponse;
use HeadlessChromium\Exception\CommunicationException\ResponseHasError;
use HeadlessChromium\Exception\JavascriptException;
use HeadlessChromium\Exception\NavigationExpired;
use HeadlessChromium\Exception\NoResponseAvailable;
use HeadlessChromium\Exception\OperationTimedOut;
use HeadlessChromium\Page;
use Psr\Http\Message\RequestInterface;

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
    ): RespondedRequest {
        $browser = $this->getBrowser($request, $proxy);

        $this->page = $browser->createPage();

        $statusCode = 200;

        $responseHeaders = [];

        $this->page->getSession()->once(
            "method:Network.responseReceived",
            function ($params) use (&$statusCode, &$responseHeaders) {
                $statusCode = $params['response']['status'];

                $responseHeaders = $this->sanitizeResponseHeaders($params['response']['headers']);
            },
        );

        $throttler->trackRequestStartFor($request->getUri());

        $this->page
            ->navigate($request->getUri()->__toString())
            ->waitForNavigation();

        $throttler->trackRequestEndFor($request->getUri());

        $html = $this->page->getHtml();

        return new RespondedRequest(
            $request,
            new Response($statusCode, $responseHeaders, $html),
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
     * @throws Exception
     */
    protected function getBrowser(
        RequestInterface $request,
        ?string $proxy = null,
    ): Browser {
        if (!$this->browser || $this->shouldRenewBrowser($proxy)) {
            $this->closeBrowser();

            $options = $this->optionsFromRequest($request, $proxy);

            $this->browser = (new BrowserFactory($this->executable))->createBrowser($options);

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

        if (isset($request->getHeader('User-Agent')[0])) {
            $options['userAgent'] = $request->getHeader('User-Agent')[0];
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
            return is_array($headerValue) ? reset($headerValue) : $headerValue;
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
}
