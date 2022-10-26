<?php

namespace Crwlr\Crawler\Steps\Loading;

use Crwlr\Crawler\Loader\Http\Messages\RespondedRequest;
use Crwlr\Crawler\Steps\Loading\Http\Paginate;
use Crwlr\Crawler\Steps\Loading\Http\Paginator;
use Crwlr\Crawler\Steps\Loading\Http\PaginatorInterface;
use Exception;
use Generator;
use GuzzleHttp\Psr7\Request;
use InvalidArgumentException;
use Psr\Http\Message\MessageInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;

class Http extends LoadingStep
{
    protected bool $stopOnErrorResponse = false;

    protected bool $yieldErrorResponses = false;

    /**
     * @param string $method
     * @param array|(string|string[])[] $headers
     * @param string|StreamInterface|null $body
     * @param string $httpVersion
     */
    public function __construct(
        protected readonly string $method = 'GET',
        protected readonly array $headers = [],
        protected readonly string|StreamInterface|null $body = null,
        protected readonly string $httpVersion = '1.1',
    ) {
    }

    /**
     * @param array|(string|string[])[] $headers
     */
    public static function crawl(array $headers = [], string $httpVersion = '1.1'): HttpCrawl
    {
        return new HttpCrawl($headers, $httpVersion);
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
     * When using the contents of an HTTP Message Stream multiple times, it's important to not forget to rewind() it,
     * otherwise you'll just get an empty string. So better just always use this helper.
     */
    public static function getBodyString(MessageInterface|RespondedRequest $message): string
    {
        $message = $message instanceof RespondedRequest ? $message->response : $message;

        $message->getBody()->rewind();

        $contents = $message->getBody()->getContents();

        $message->getBody()->rewind();

        return $contents;
    }

    public function paginate(
        PaginatorInterface|string $paginator,
        int $defaultPaginatorMaxPages = Paginator::MAX_PAGES_DEFAULT
    ): Paginate {
        if (is_string($paginator)) {
            $paginator = Paginator::simpleWebsite($paginator, $defaultPaginatorMaxPages);
        }

        return new Paginate($paginator, $this->method, $this->headers, $this->body, $this->httpVersion);
    }

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
     * @throws InvalidArgumentException
     */
    protected function validateAndSanitizeInput(mixed $input): UriInterface
    {
        return $this->validateAndSanitizeToUriInterface($input);
    }

    /**
     * @return Generator<RespondedRequest>
     * @throws Exception
     */
    protected function invoke(mixed $input): Generator
    {
        $response = $this->getResponseFromInputUri($input);

        if ($response) {
            yield $response;
        }
    }

    protected function getResponseFromInputUri(UriInterface $input): ?RespondedRequest
    {
        $request = $this->getRequestFromInputUri($input);

        return $this->getResponseFromRequest($request);
    }

    protected function getRequestFromInputUri(UriInterface $uri): RequestInterface
    {
        return new Request($this->method, $uri, $this->headers, $this->body, $this->httpVersion);
    }

    protected function getResponseFromRequest(RequestInterface $request): ?RespondedRequest
    {
        if ($this->stopOnErrorResponse) {
            $response = $this->loader->loadOrFail($request);
        } else {
            $response = $this->loader->load($request);
        }

        if ($response !== null && ($response->response->getStatusCode() < 400 || $this->yieldErrorResponses)) {
            return $response;
        }

        return null;
    }
}
