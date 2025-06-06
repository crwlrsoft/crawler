<?php

namespace Crwlr\Crawler\Loader\Http\Messages;

use Crwlr\Crawler\Cache\Exceptions\MissingZlibExtensionException;
use Crwlr\Crawler\Loader\Http\Browser\Screenshot;
use Crwlr\Crawler\Steps\Loading\Http;
use Crwlr\Crawler\Utils\RequestKey;
use Crwlr\Url\Url;
use Exception;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class RespondedRequest
{
    /**
     * @var string[]
     */
    protected array $redirects = [];

    protected bool $isServedFromCache = false;

    /**
     * @param Screenshot[] $screenshots
     * @throws Exception
     */
    public function __construct(
        public RequestInterface $request,
        public ResponseInterface $response,
        public array $screenshots = [],
    ) {
        $this->setResponse($this->response);
    }

    /**
     * @param mixed[] $data
     * @return RespondedRequest
     * @throws Exception
     */
    public static function fromArray(array $data): RespondedRequest
    {
        $respondedRequest = new RespondedRequest(
            self::requestFromArray($data),
            self::responseFromArray($data),
            self::screenshotsFromArray($data),
        );

        if ($data['effectiveUri'] && $data['effectiveUri'] !== $data['requestUri']) {
            $respondedRequest->addRedirectUri($data['effectiveUri']);
        }

        return $respondedRequest;
    }

    /**
     * @return mixed[]
     * @throws MissingZlibExtensionException
     */
    public function __serialize(): array
    {
        return [
            'requestMethod' => $this->request->getMethod(),
            'requestUri' => $this->request->getUri()->__toString(),
            'requestHeaders' => $this->request->getHeaders(),
            'requestBody' => Http::getBodyString($this->request),
            'effectiveUri' => $this->effectiveUri(),
            'responseStatusCode' => $this->response->getStatusCode(),
            'responseHeaders' => $this->response->getHeaders(),
            'responseBody' => Http::getBodyString($this->response),
            'screenshots' => array_map(fn(Screenshot $screenshot) => $screenshot->path, $this->screenshots),
        ];
    }

    /**
     * @return mixed[]
     * @throws MissingZlibExtensionException
     */
    public function toArrayForResult(): array
    {
        $serialized = $this->__serialize();

        $mapping = [
            'url' => 'effectiveUri',
            'uri' => 'effectiveUri',
            'status' => 'responseStatusCode',
            'headers' => 'responseHeaders',
            'body' => 'responseBody',
        ];

        foreach ($mapping as $newKey => $originalKey) {
            $serialized[$newKey] = $serialized[$originalKey];
        }

        return $serialized;
    }

    /**
     * @param mixed[] $data
     * @throws Exception
     */
    public function __unserialize(array $data): void
    {
        $this->request = self::requestFromArray($data);

        $this->response = self::responseFromArray($data);

        if ($data['effectiveUri'] && $data['effectiveUri'] !== $data['requestUri']) {
            $this->addRedirectUri($data['effectiveUri']);
        }

        $this->screenshots = self::screenshotsFromArray($data);
    }

    public function effectiveUri(): string
    {
        return empty($this->redirects) ? $this->requestedUri() : end($this->redirects);
    }

    public function requestedUri(): string
    {
        return $this->request->getUri();
    }

    /**
     * @return array<int, string>
     */
    public function allUris(): array
    {
        $uris = [$this->requestedUri() => $this->requestedUri()];

        foreach ($this->redirects as $redirect) {
            $uris[$redirect] = $redirect;
        }

        return array_values($uris);
    }

    public function isRedirect(): bool
    {
        return $this->response->getStatusCode() >= 300 && $this->response->getStatusCode() < 400;
    }

    /**
     * @return string[]
     */
    public function redirects(): array
    {
        return $this->redirects;
    }

    /**
     * @throws Exception
     */
    public function setResponse(ResponseInterface $response): void
    {
        $this->response = $response;

        if ($this->isRedirect()) {
            $this->addRedirectUri();
        }
    }

    /**
     * @throws Exception
     */
    public function addRedirectUri(?string $redirectUri = null): void
    {
        $redirectUri = Url::parse($this->effectiveUri())
            ->resolve($redirectUri ?? $this->response->getHeaderLine('Location'))
            ->__toString();

        // Add it only if different from the previous one.
        if ($redirectUri !== end($this->redirects)) {
            $this->redirects[] = $redirectUri;
        }
    }

    public function cacheKey(): string
    {
        return RequestKey::from($this->request);
    }

    public function isServedFromCache(): bool
    {
        return $this->isServedFromCache;
    }

    public function setIsServedFromCache(bool $value = true): void
    {
        $this->isServedFromCache = $value;
    }

    /**
     * @param mixed[] $data
     */
    protected static function requestFromArray(array $data): Request
    {
        return new Request(
            $data['requestMethod'],
            $data['requestUri'],
            $data['requestHeaders'],
            $data['requestBody'],
        );
    }

    /**
     * @param mixed[] $data
     */
    protected static function responseFromArray(array $data): Response
    {
        return new Response(
            $data['responseStatusCode'],
            $data['responseHeaders'],
            $data['responseBody'],
        );
    }

    /**
     * @param mixed[] $data
     * @return Screenshot[]
     */
    protected static function screenshotsFromArray(array $data): array
    {
        $screenshots = [];

        if (array_key_exists('screenshots', $data)) {
            foreach ($data['screenshots'] as $screenshot) {
                if (file_exists($screenshot)) {
                    $screenshots[] = new Screenshot($screenshot);
                }
            }
        }

        return $screenshots;
    }
}
