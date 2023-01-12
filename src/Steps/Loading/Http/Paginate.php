<?php

namespace Crwlr\Crawler\Steps\Loading\Http;

use Crwlr\Crawler\Steps\Loading\Http;
use Crwlr\Url\Url;
use Generator;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;

class Paginate extends Http
{
    public function __construct(
        protected Http\PaginatorInterface $paginator,
        string $method = 'GET',
        array $headers = [],
        string|StreamInterface|null $body = null,
        string $httpVersion = '1.1',
    ) {
        parent::__construct($method, $headers, $body, $httpVersion);
    }

    /**
     * @param UriInterface $input
     */
    protected function invoke(mixed $input): Generator
    {
        $request = $this->paginator->prepareRequest($this->getRequestFromInputUri($input));

        $response = $this->getResponseFromRequest($request);

        if ($response) {
            yield $response;
        }

        $this->paginator->processLoaded($input, $request, $response);

        while (!$this->paginator->hasFinished()) {
            $nextUrl = $this->paginator->getNextUrl();

            if (!$nextUrl) {
                break;
            }

            $nextUrl = Url::parsePsr7($nextUrl);

            $request = $this->paginator->prepareRequest($this->getRequestFromInputUri($nextUrl), $response);

            $response = $this->getResponseFromRequest($request);

            if ($response) {
                yield $response;
            }

            $this->paginator->processLoaded($nextUrl, $request, $response);
        }

        if ($this->logger) {
            $this->paginator->logWhenFinished($this->logger);
        }
    }

    /**
     * @param mixed $input
     * @return mixed
     */
    protected function validateAndSanitizeInput(mixed $input): mixed
    {
        return $this->validateAndSanitizeToUriInterface($input);
    }
}
