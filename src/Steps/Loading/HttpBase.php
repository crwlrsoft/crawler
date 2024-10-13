<?php

namespace Crwlr\Crawler\Steps\Loading;

use Crwlr\Crawler\Loader\Http\Exceptions\LoadingException;
use Crwlr\Crawler\Loader\Http\HttpLoader;
use Crwlr\Crawler\Loader\Http\Messages\RespondedRequest;
use Crwlr\Crawler\Steps\Step;
use Crwlr\Crawler\Utils\HttpHeaders;
use GuzzleHttp\Psr7\Request;
use InvalidArgumentException;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;

abstract class HttpBase extends Step
{
    /**
     * @use LoadingStep<HttpLoader>
     */
    use LoadingStep;

    protected bool $stopOnErrorResponse = false;

    protected bool $yieldErrorResponses = false;

    protected ?string $useAsUrl = null;

    protected ?string $useAsBody = null;

    protected ?string $inputBody = null;

    protected ?string $useAsHeaders = null;

    /**
     * @var null|array<string, string>
     */
    protected ?array $useAsHeader = null;

    /**
     * @var null|array<string, string|string[]>
     */
    protected ?array $inputHeaders = null;

    /**
     * @param string $method
     * @param array<string, string|string[]> $headers
     * @param string|StreamInterface|null $body
     * @param string $httpVersion
     */
    public function __construct(
        protected readonly string $method = 'GET',
        protected readonly array $headers = [],
        protected readonly string|StreamInterface|null $body = null,
        protected readonly string $httpVersion = '1.1',
    ) {}

    public function stopOnErrorResponse(): static
    {
        $this->stopOnErrorResponse = true;

        return $this;
    }

    public function yieldErrorResponses(): static
    {
        $this->yieldErrorResponses = true;

        return $this;
    }

    /**
     * Chose key from array input to use its value as request URL
     *
     * If input is an array with string keys, you can define which key from that array should be used as the URL for
     * the HTTP request.
     */
    public function useInputKeyAsUrl(string $key): static
    {
        $this->useAsUrl = $key;

        return $this;
    }

    /**
     * Chose key from array input to use its value as request body
     *
     * If input is an array with string keys, you can define which key from that array should be used as the body for
     * the HTTP request.
     */
    public function useInputKeyAsBody(string $key): static
    {
        $this->useAsBody = $key;

        return $this;
    }

    /**
     * Chose key from array input to use its value as a request header
     *
     * If input is an array with string keys, you can choose a key from that array and map it to an HTTP request header.
     */
    public function useInputKeyAsHeader(string $key, string $asHeader = null): static
    {
        $asHeader = $asHeader ?? $key;

        if ($this->useAsHeader === null) {
            $this->useAsHeader = [];
        }

        $this->useAsHeader[$key] = $asHeader;

        return $this;
    }

    /**
     * Chose key from array input to use its value as request headers
     *
     * If input is an array with string keys, you can choose a key from that array that will be used as headers for the
     * HTTP request. So, the value behind that array key, has to be an array with header names as keys. If you want to
     * map just one single HTTP header from input, use the `useInputKeyAsHeader()` method.
     */
    public function useInputKeyAsHeaders(string $key): static
    {
        $this->useAsHeaders = $key;

        return $this;
    }

    /**
     * @return UriInterface|UriInterface[]
     * @throws InvalidArgumentException
     */
    protected function validateAndSanitizeInput(mixed $input): mixed
    {
        $this->getBodyFromArrayInput($input);

        $this->getHeadersFromArrayInput($input);

        $input = $this->getUrlFromArrayInput($input);

        if (is_array($input)) {
            foreach ($input as $key => $url) {
                $input[$key] = $this->validateAndSanitizeToUriInterface($url);
            }

            return $input;
        }

        return $this->validateAndSanitizeToUriInterface($input);
    }

    protected function outputKeyAliases(): array
    {
        return [
            'url' => 'effectiveUri',
            'uri' => 'effectiveUri',
            'status' => 'responseStatusCode',
            'headers' => 'responseHeaders',
            'body' => 'responseBody',
        ];
    }

    /**
     * @throws LoadingException
     */
    protected function getResponseFromInputUri(UriInterface $input): ?RespondedRequest
    {
        $request = $this->getRequestFromInputUri($input);

        return $this->getResponseFromRequest($request);
    }

    protected function getRequestFromInputUri(UriInterface $uri): RequestInterface
    {
        $body = $this->inputBody ?? (property_exists($this, 'body') ? $this->body : '');

        $headers = $this->mergeHeaders();

        return new Request($this->method, $uri, $headers, $body, $this->httpVersion);
    }

    /**
     * @throws LoadingException
     */
    protected function getResponseFromRequest(RequestInterface $request): ?RespondedRequest
    {
        if ($this->stopOnErrorResponse) {
            $response = $this->getLoader()->loadOrFail($request);
        } else {
            $response = $this->getLoader()->load($request);
        }

        if ($response !== null && ($response->response->getStatusCode() < 400 || $this->yieldErrorResponses)) {
            return $response;
        }

        return null;
    }

    /**
     * @return mixed
     */
    protected function getUrlFromArrayInput(mixed $input): mixed
    {
        if ($this->useAsUrl) {
            if (!is_array($input)) {
                $this->logger?->warning('Input is not array, therefore can\'t get URL from input by key.');
            } elseif (array_key_exists($this->useAsUrl, $input)) {
                return [$input[$this->useAsUrl]];
            } else {
                $this->logger?->warning(
                    'Input key ' . $this->useAsUrl . ' that should be used as request URL isn\'t present in input.',
                );
            }
        } elseif (is_array($input) && array_key_exists('url', $input)) {
            return $input['url'];
        } elseif (is_array($input) && array_key_exists('uri', $input)) {
            return $input['uri'];
        }

        return $input;
    }

    protected function getBodyFromArrayInput(mixed $input): void
    {
        if ($this->useAsBody) {
            if (!is_array($input)) {
                $this->logger?->warning('Input is not array, therefore can\'t get body from input by key.');
            } elseif (array_key_exists($this->useAsBody, $input)) {
                $this->inputBody = $input[$this->useAsBody];
            } else {
                $this->logger?->warning(
                    'Input key ' . $this->useAsBody . ' that should be used as request body isn\'t present in input.',
                );
            }
        }
    }

    protected function getHeadersFromArrayInput(mixed $input): void
    {
        if ($this->useAsHeaders) {
            if (!is_array($input)) {
                $this->logger?->warning('Input is not array, therefore can\'t get headers from input by key.');
            } elseif (array_key_exists($this->useAsHeaders, $input)) {
                $this->inputHeaders = $input[$this->useAsHeaders];
            } else {
                $this->logger?->warning(
                    'Input key ' . $this->useAsHeaders . ' that should be used as request headers isn\'t present in ' .
                    'input.',
                );
            }
        }

        if (is_array($this->useAsHeader)) {
            if (!is_array($input)) {
                $this->logger?->warning('Input is not array, therefore can\'t get header from input by key.');
            } else {
                foreach ($this->useAsHeader as $inputKey => $headerName) {
                    $this->addToInputHeadersFromInput($input, $inputKey, $headerName);
                }
            }
        }
    }

    protected function addToInputHeadersFromInput(mixed $input, string $inputKey, string $headerName): void
    {
        if (!is_array($this->inputHeaders)) {
            $this->inputHeaders = [];
        }

        if (!array_key_exists($inputKey, $input)) {
            $this->logger?->warning(
                'Input key ' . $inputKey . ' that should be used as a request header, isn\'t present in input.',
            );

            return;
        }

        $inputValue = $input[$inputKey];

        if (!array_key_exists($headerName, $this->inputHeaders)) {
            $this->inputHeaders[$headerName] = is_array($inputValue) ? $inputValue : [$inputValue];

            return;
        }

        $this->inputHeaders = HttpHeaders::addTo(HttpHeaders::normalize($this->inputHeaders), $headerName, $inputValue);
    }

    /**
     * @return array<string, string[]>
     */
    protected function mergeHeaders(): array
    {
        $headers = HttpHeaders::normalize($this->headers);

        if (is_array($this->inputHeaders)) {
            $inputHeaders = HttpHeaders::normalize($this->inputHeaders);

            $headers = HttpHeaders::merge($headers, $inputHeaders);
        }

        return $headers;
    }

    protected function resetInputRequestParams(): void
    {
        $this->inputHeaders = null;

        $this->inputBody = null;
    }
}
