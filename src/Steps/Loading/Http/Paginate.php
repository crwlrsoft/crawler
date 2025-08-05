<?php

namespace Crwlr\Crawler\Steps\Loading\Http;

use Crwlr\Crawler\Loader\Http\Exceptions\LoadingException;
use Crwlr\Crawler\Loader\Http\Messages\RespondedRequest;
use Crwlr\Crawler\Steps\Loading\Http;
use Exception;
use Generator;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;

/**
 * @deprecated This class shall be removed in the next major version (v4).
 *             See the comment above the Http::transferSettingsToPaginateStep() method.
 */

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
     * @param UriInterface|UriInterface[] $input
     * @throws LoadingException
     */
    protected function invoke(mixed $input): Generator
    {
        if (is_array($input)) {
            foreach ($input as $inputUrl) {
                yield from $this->paginateInputUrl($inputUrl);
            }
        } else {
            yield from $this->paginateInputUrl($input);
        }
    }

    /**
     * @throws LoadingException
     */
    private function paginateInputUrl(UriInterface $url): Generator
    {
        $request = $this->getRequestFromInputUri($url);

        $response = $this->getResponseFromRequest($request);

        if ($response) {
            yield $response;
        }

        $this->processLoaded($request, $response);

        while (!$this->paginator->hasFinished()) {
            $request = $this->paginator->getNextRequest();

            if (!$request) {
                break;
            }

            $response = $this->getResponseFromRequest($request);

            if ($response) {
                yield $response;
            }

            $this->processLoaded($request, $response);
        }

        $this->finish();
    }

    private function finish(): void
    {
        if ($this->logger) {
            $this->paginator->logWhenFinished($this->logger);

            if (method_exists($this->paginator, 'resetFinished')) {
                $this->paginator->resetFinished();
            }
        }
    }

    private function processLoaded(RequestInterface $request, ?RespondedRequest $response): void
    {
        try {
            $this->paginator->processLoaded($request, $response);
        } catch (Exception $exception) {
            $this->logger?->error('Paginate Error: ' . $exception->getMessage());
        }
    }
}
