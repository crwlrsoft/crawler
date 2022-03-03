<?php

namespace Crwlr\Crawler\Steps\Loading;

use Crwlr\Crawler\Aggregates\RequestResponseAggregate;
use Crwlr\Crawler\Input;
use Crwlr\Url\Url;
use Exception;
use Generator;
use GuzzleHttp\Psr7\Request;
use InvalidArgumentException;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;

class Http extends LoadingStep
{
    protected RequestInterface $request;

    /**
     * @param string $method
     * @param array|(string|string[])[] $headers
     * @param string|StreamInterface|null $body
     * @param string $httpVersion
     */
    public function __construct(
        string $method,
        array $headers = [],
        string|StreamInterface|null $body = null,
        string $httpVersion = '1.1',
    ) {
        $this->request = new Request($method, '/', $headers, $body, $httpVersion);
    }

    /**
     * @param array|(string|string[])[] $headers
     */
    public static function get(array $headers = [], string $httpVersion = '1.1'): self
    {
        return new self('GET', $headers, null, $httpVersion);
    }

    /**
     * @param array|(string|string[])[] $headers
     */
    public static function post(
        array $headers = [],
        string|StreamInterface|null $body = null,
        string $httpVersion = '1.1',
    ): self {
        return new self('POST', $headers, $body, $httpVersion);
    }

    /**
     * @param array|(string|string[])[] $headers
     */
    public static function put(
        array $headers = [],
        string|StreamInterface|null $body = null,
        string $httpVersion = '1.1',
    ): self {
        return new self('PUT', $headers, $body, $httpVersion);
    }

    /**
     * @param array|(string|string[])[] $headers
     */
    public static function patch(
        array $headers = [],
        string|StreamInterface|null $body = null,
        string $httpVersion = '1.1',
    ): self {
        return new self('PATCH', $headers, $body, $httpVersion);
    }

    /**
     * @param array|(string|string[])[] $headers
     */
    public static function delete(
        array $headers = [],
        string|StreamInterface|null $body = null,
        string $httpVersion = '1.1',
    ): self {
        return new self('DELETE', $headers, $body, $httpVersion);
    }

    /**
     * @throws InvalidArgumentException
     */
    protected function validateAndSanitizeInput(Input $input): mixed
    {
        $inputValue = $input->get();

        if ($inputValue instanceof UriInterface) {
            return $inputValue;
        }

        if (is_string($inputValue)) {
            return Url::parsePsr7($inputValue);
        }

        throw new InvalidArgumentException('Input must be string or an instance of the PSR-7 UriInterface');
    }

    /**
     * @return Generator<RequestResponseAggregate|null>
     * @throws Exception
     */
    protected function invoke(Input $input): Generator
    {
        $request = $this->request->withUri($input->get());

        yield $this->loader->load($request);
    }
}
