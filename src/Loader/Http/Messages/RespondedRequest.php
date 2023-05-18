<?php

namespace Crwlr\Crawler\Loader\Http\Messages;

use Crwlr\Crawler\Steps\Loading\Http;
use Crwlr\Url\Url;
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

    public function __construct(
        public RequestInterface $request,
        public ResponseInterface $response,
    ) {
        $this->setResponse($this->response);
    }

    /**
     * @param mixed[] $data
     * @return RespondedRequest
     */
    public static function fromArray(array $data): RespondedRequest
    {
        $respondedRequest = new RespondedRequest(
            self::requestFromArray($data),
            self::responseFromArray($data),
        );

        if ($data['effectiveUri'] && $data['effectiveUri'] !== $data['requestUri']) {
            $respondedRequest->addRedirectUri($data['effectiveUri']);
        }

        return $respondedRequest;
    }

    public static function cacheKeyFromRequest(RequestInterface $request): string
    {
        return self::cacheKeyFromRequestData(self::requestDataFromRequest($request));
    }

    /**
     * @return mixed[]
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
        ];
    }

    /**
     * @param mixed[] $data
     */
    public function __unserialize(array $data): void
    {
        $this->request = self::requestFromArray($data);

        $this->response = self::responseFromArray($data);

        if ($data['effectiveUri'] && $data['effectiveUri'] !== $data['requestUri']) {
            $this->addRedirectUri($data['effectiveUri']);
        }
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

    public function setResponse(ResponseInterface $response): void
    {
        $this->response = $response;

        if ($this->isRedirect()) {
            $this->addRedirectUri();
        }
    }

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
        return self::cacheKeyFromRequestData(self::requestDataFromRequest($this->request));
    }

    /**
     * @return mixed[]
     */
    protected static function requestDataFromRequest(RequestInterface $request): array
    {
        return [
            'requestMethod' => $request->getMethod(),
            'requestUri' => $request->getUri()->__toString(),
            'requestHeaders' => $request->getHeaders(),
            'requestBody' => Http::getBodyString($request),
        ];
    }

    /**
     * @param mixed[] $requestData
     */
    protected static function cacheKeyFromRequestData(array $requestData): string
    {
        // Remove cookies when building the key, so cache doesn't depend on sessions
        if (isset($requestData['requestHeaders']['Cookie'])) {
            unset($requestData['requestHeaders']['Cookie']);
        }

        if (isset($requestData['requestHeaders']['cookie'])) {
            unset($requestData['requestHeaders']['cookie']);
        }

        $serialized = serialize($requestData);

        return md5($serialized);
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
}
