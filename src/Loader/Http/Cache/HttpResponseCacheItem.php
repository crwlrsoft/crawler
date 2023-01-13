<?php

namespace Crwlr\Crawler\Loader\Http\Cache;

use Crwlr\Crawler\Loader\Http\Messages\RespondedRequest;
use Crwlr\Crawler\Steps\Loading\Http;
use Exception;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use InvalidArgumentException;
use Psr\Http\Message\MessageInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

final class HttpResponseCacheItem
{
    private string $key;

    /**
     * @param string $requestMethod
     * @param string $requestUri
     * @param array|(string|string[])[] $requestHeaders
     * @param string $requestBody
     * @param string $effectiveUri
     * @param int $responseStatusCode
     * @param array|(string|string[])[] $responseHeaders
     * @param string $responseBody
     */
    public function __construct(
        private readonly string $requestMethod,
        private readonly string $requestUri,
        private readonly array  $requestHeaders,
        private readonly string $requestBody,
        private readonly string $effectiveUri,
        private readonly int    $responseStatusCode,
        private readonly array  $responseHeaders,
        private readonly string $responseBody,
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

    public static function fromRespondedRequest(RespondedRequest $respondedRequest): self
    {
        return new self(
            $respondedRequest->request->getMethod(),
            $respondedRequest->requestedUri(),
            $respondedRequest->request->getHeaders(),
            self::copyBody($respondedRequest->request),
            $respondedRequest->effectiveUri(),
            $respondedRequest->response->getStatusCode(),
            $respondedRequest->response->getHeaders(),
            self::copyBody($respondedRequest->response),
        );
    }

    /**
     * @throws Exception
     */
    public static function fromSerialized(string $serialized): self
    {
        $unserialized = unserialize($serialized);

        if (is_array($unserialized)) {
            return self::fromArray($unserialized);
        }

        throw new Exception('Can only be created from a serialized array of data');
    }

    /**
     * @param array|mixed[] $array
     * @return static
     * @throws InvalidArgumentException
     */
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
        return Http::getBodyString($httpMessage);
    }

    public function key(): string
    {
        return $this->key;
    }

    /**
     * @return mixed[]
     */
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

    public function respondedRequest(): RespondedRequest
    {
        $respondedRequest = new RespondedRequest($this->request(), $this->response());

        if ($this->effectiveUri() !== $respondedRequest->effectiveUri()) {
            $respondedRequest->addRedirectUri($this->effectiveUri());
        }

        return $respondedRequest;
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

    /**
     * @param mixed[] $requestData
     */
    private static function keyFromRequestData(array $requestData): string
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
     * @return mixed[]
     */
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
