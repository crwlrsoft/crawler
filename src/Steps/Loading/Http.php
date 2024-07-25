<?php

namespace Crwlr\Crawler\Steps\Loading;

use Crwlr\Crawler\Loader\Http\Messages\RespondedRequest;
use Crwlr\Crawler\Steps\Html\Exceptions\InvalidDomQueryException;
use Crwlr\Crawler\Steps\Loading\Http\AbstractPaginator;
use Crwlr\Crawler\Steps\Loading\Http\Paginate;
use Crwlr\Crawler\Steps\Loading\Http\Paginator;
use Crwlr\Crawler\Steps\Loading\Http\PaginatorInterface;
use Crwlr\Crawler\Steps\StepOutputType;
use Exception;
use Generator;
use Psr\Http\Message\MessageInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;

class Http extends HttpBase
{
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

        $isEncoded = 0 === mb_strpos($contents, "\x1f" . "\x8b" . "\x08", 0, 'US-ASCII');

        if (in_array('application/x-gzip', $message->getHeader('Content-Type'), true) && $isEncoded && function_exists('gzdecode')) {
            // Temporarily set a new error handler, so decoding a string that actually isn't compressed, doesn't
            // generate a warning.
            $previousHandler = set_error_handler(function ($errno, $errstr) {
                return $errno === E_WARNING && str_contains($errstr, 'gzdecode(): data error');
            });

            $decoded = gzdecode($contents);

            set_error_handler($previousHandler);

            if ($decoded !== false) {
                return $decoded;
            }
        }

        return $contents;
    }

    /**
     * @throws InvalidDomQueryException
     */
    public function paginate(
        PaginatorInterface|AbstractPaginator|string $paginator,
        int $defaultPaginatorMaxPages = Paginator::MAX_PAGES_DEFAULT,
    ): Paginate {
        if (is_string($paginator)) {
            $paginator = Paginator::simpleWebsite($paginator, $defaultPaginatorMaxPages);
        }

        return new Paginate($paginator, $this->method, $this->headers, $this->body, $this->httpVersion);
    }

    public function outputType(): StepOutputType
    {
        return StepOutputType::AssociativeArrayOrObject;
    }

    /**
     * @param UriInterface|UriInterface[] $input
     * @return Generator<RespondedRequest>
     * @throws Exception
     */
    protected function invoke(mixed $input): Generator
    {
        $input = !is_array($input) ? [$input] : $input;

        foreach ($input as $uri) {
            $response = $this->getResponseFromInputUri($uri);

            if ($response) {
                yield $response;
            }
        }

        $this->resetInputRequestParams();
    }
}
