<?php

namespace Crwlr\Crawler\Loader\Http;

use Exception;
use HeadlessChromium\Browser;
use HeadlessChromium\BrowserFactory;
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

    protected ?string $proxy = null;

    /**
     * @throws Exception
     */
    public function getBrowser(
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
