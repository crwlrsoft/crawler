<?php

namespace Crwlr\Crawler\Steps\Loading\Http;

use Crwlr\Crawler\Loader\Http\Exceptions\LoadingException;
use Crwlr\Crawler\Steps\Loading\Http;
use Exception;
use Generator;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;

class Paginate extends Http
{
    public function __construct(
        protected AbstractPaginator $paginator,
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
            $this->paginator->processLoaded($request, $response);
        } catch (Exception $exception) {
            $this->logger?->error('Paginate Error: ' . $exception->getMessage());
        }

        while (!$this->paginator->hasFinished()) {
            $request = $this->paginator->getNextRequest();

            if (!$request) {
                break;
            }

            $response = $this->getResponseFromRequest($request);

            if ($response) {
                yield $response;
            }

            try {
                $this->paginator->processLoaded($request, $response);
            } catch (Exception $exception) {
                $this->logger?->error('Paginate Error: ' . $exception->getMessage());
            }
        }

        if ($this->logger) {
            $this->paginator->logWhenFinished($this->logger);

            if (method_exists($this->paginator, 'resetFinished')) {
                $this->paginator->resetFinished();
            }
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
}
