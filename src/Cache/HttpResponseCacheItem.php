<?php

namespace Crwlr\Crawler\Cache;

use Crwlr\Crawler\Aggregates\RequestResponseAggregate;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Utils;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class HttpResponseCacheItem
{
    private string $requestMethod;
    private string $requestUri;
    private array $requestHeaders;
    private string $effectiveUri;
    private int $responseStatusCode;
    private array $responseHeaders;
    private string $responseBody;

    public function __construct(RequestResponseAggregate $requestResponseAggregate) {
        $this->requestMethod = $requestResponseAggregate->request->getMethod();
        $this->requestUri = $requestResponseAggregate->requestedUri();
        $this->requestHeaders = $requestResponseAggregate->request->getHeaders();
        $this->effectiveUri = $requestResponseAggregate->effectiveUri();
        $this->responseStatusCode = $requestResponseAggregate->response->getStatusCode();
        $this->responseHeaders = $requestResponseAggregate->response->getHeaders();
        $this->responseBody = $requestResponseAggregate->response->getBody()->getContents();

        // Reading the response body to a string empties it in the response object, so add it again.
        $bodyStream = Utils::streamFor($this->responseBody);
        $requestResponseAggregate->setResponse($requestResponseAggregate->response->withBody($bodyStream));
    }

    public static function fromSerialized(string $serialized): self
    {
        return unserialize($serialized);
    }

    public static function cacheKeyFromRequest(RequestInterface $request): string
    {
        $requestData = [
            'method' => $request->getMethod(),
            'uri' => $request->getUri()->__toString(),
            'headers' => $request->getHeaders(),
        ];
        $serialized = serialize($requestData);

        return hash("crc32b", $serialized);
    }

    public function cacheKey(): string
    {
        return self::cacheKeyFromRequest($this->request());
    }

    public function serialize(): string
    {
        return serialize($this);
    }

    public function aggregate(): RequestResponseAggregate
    {
        return new RequestResponseAggregate($this->request(), $this->response());
    }

    public function request(): RequestInterface
    {
        return new Request($this->requestMethod, $this->requestUri, $this->requestHeaders);
    }

    public function response(): ResponseInterface
    {
        return new Response($this->responseStatusCode, $this->responseHeaders, $this->responseBody);
    }

    public function effectiveUri(): string
    {
        return $this->effectiveUri;
    }
}
