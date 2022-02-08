<?php

namespace Crwlr\Crawler\Cache;

use Crwlr\Crawler\Aggregates\RequestResponseAggregate;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\MessageInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class HttpResponseCacheItem
{
    private string $key;

    public function __construct(
        private string $requestMethod,
        private string $requestUri,
        private array $requestHeaders,
        private string $requestBody,
        private string $effectiveUri,
        private int $responseStatusCode,
        private array $responseHeaders,
        private string $responseBody,
    ) {
        $this->key = self::keyFromRequestData($this->requestProperties());
    }

    public static function keyFromRequest(RequestInterface $request): string
    {
        return self::keyFromRequestData([
            'requestMethod' => $request->getMethod(),
            'requestUri' => $request->getUri()->__toString(),
            'requestHeaders' => $request->getHeaders(),
            'requestBody' => self::copyBody($request),
        ]);
    }

    public static function fromAggregate(RequestResponseAggregate $aggregate): self
    {
        return new self(
            $aggregate->request->getMethod(),
            $aggregate->requestedUri(),
            $aggregate->request->getHeaders(),
            self::copyBody($aggregate->request),
            $aggregate->effectiveUri(),
            $aggregate->response->getStatusCode(),
            $aggregate->response->getHeaders(),
            self::copyBody($aggregate->response),
        );
    }

    public static function fromSerialized(string $serialized): self
    {
        return self::fromArray(unserialize($serialized));
    }

    public static function fromArray(array $array): self
    {
        return new self(...$array);
    }

    /**
     * When reading the body stream of an HTTP message to a string, you need to rewind the stream afterwards, otherwise
     * it returns an empty string on a consecutive call.
     */
    public static function copyBody(MessageInterface $httpMessage): string
    {
        $httpMessage->getBody()->rewind();
        $body = $httpMessage->getBody()->getContents();
        $httpMessage->getBody()->rewind();

        return $body;
    }

    public function key(): string
    {
        return $this->key;
    }

    public function toArray(): array
    {
        return [
            'requestMethod' => $this->requestMethod,
            'requestUri' => $this->requestUri,
            'requestHeaders' => $this->requestHeaders,
            'requestBody' => $this->requestBody,
            'effectiveUri' => $this->effectiveUri,
            'responseStatusCode' => $this->responseStatusCode,
            'responseHeaders' => $this->responseHeaders,
            'responseBody' => $this->responseBody,
        ];
    }

    public function serialize(): string
    {
        return serialize($this->toArray());
    }

    public function aggregate(): RequestResponseAggregate
    {
        $aggregate = new RequestResponseAggregate($this->request(), $this->response());

        if ($this->effectiveUri() !== $aggregate->effectiveUri()) {
            $aggregate->addRedirectUri($this->effectiveUri());
        }

        return $aggregate;
    }

    public function request(): RequestInterface
    {
        return new Request($this->requestMethod, $this->requestUri, $this->requestHeaders, $this->requestBody);
    }

    public function response(): ResponseInterface
    {
        return new Response($this->responseStatusCode, $this->responseHeaders, $this->responseBody);
    }

    public function effectiveUri(): string
    {
        return $this->effectiveUri;
    }

    private static function keyFromRequestData(array $requestData): string
    {
        $serialized = serialize($requestData);

        return md5($serialized);
    }

    private function requestProperties(): array
    {
        return [
            'requestMethod' => $this->requestMethod,
            'requestUri' => $this->requestUri,
            'requestHeaders' => $this->requestHeaders,
            'requestBody' => $this->requestBody,
        ];
    }
}
