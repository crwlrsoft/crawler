<?php

namespace Crwlr\Crawler\Steps\Loading\Http;

use Crwlr\Crawler\Loader\Http\Exceptions\LoadingException;
use Crwlr\Crawler\Loader\Http\Messages\RespondedRequest;
use Crwlr\Crawler\Steps\Loading\Http;
use Crwlr\Url\Url;
use Exception;
use Generator;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;

class Paginate extends Http
{
    public function __construct(
        protected Http\PaginatorInterface|AbstractPaginator $paginator,
        string $method = 'GET',
        array $headers = [],
        string|StreamInterface|null $body = null,
        string $httpVersion = '1.1',
    ) {
        parent::__construct($method, $headers, $body, $httpVersion);
    }

    /**
     * @param UriInterface $input
     * @throws LoadingException
     */
    protected function invoke(mixed $input): Generator
    {
        $request = $this->getRequestFromInput($input);

        $response = $this->getResponseFromRequest($request);

        if ($response) {
            yield $response;
        }

        try {
            $this->paginator->processLoaded($input, $request, $response);
        } catch (Exception $exception) {
            $this->logger?->error('Paginate Error: ' . $exception->getMessage());
        }

        while (!$this->paginator->hasFinished()) {
            if (!method_exists($this->paginator, 'getNextRequest')) { // Remove in v2
                $request = $this->getNextRequestLegacy($response);
            } else {
                $request = $this->paginator->getNextRequest();
            }

            if (!$request) {
                break;
            }

            $response = $this->getResponseFromRequest($request);

            if ($response) {
                yield $response;
            }

            try {
                $this->paginator->processLoaded($request->getUri(), $request, $response);
            } catch (Exception $exception) {
                $this->logger?->error('Paginate Error: ' . $exception->getMessage());
            }
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

    protected function getRequestFromInput(mixed $input): RequestInterface
    {
        if (method_exists($this->paginator, 'prepareRequest')) {
            return $this->paginator->prepareRequest($this->getRequestFromInputUri($input));
        }

        return $this->getRequestFromInputUri($input);
    }

    /**
     * @deprecated Legacy method, remove in v2
     */
    protected function getNextRequestLegacy(?RespondedRequest $previousResponse): ?RequestInterface
    {
        if (!method_exists($this->paginator, 'getNextUrl')) {
            return null;
        }

        $nextUrl = $this->paginator->getNextUrl();

        if (!$nextUrl) {
            return null;
        }

        $request = $this->getRequestFromInputUri(Url::parsePsr7($nextUrl));

        if (method_exists($this->paginator, 'prepareRequest')) {
            $request = $this->paginator->prepareRequest($request, $previousResponse);
        }

        return $request;
    }
}
