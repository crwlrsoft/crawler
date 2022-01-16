<?php

namespace Crwlr\Crawler\Aggregates;

use Crwlr\Url\Url;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class RequestResponseAggregate
{
    /**
     * @var string[]
     */
    private array $redirects = [];

    public function __construct(
        public RequestInterface $request,
        public ResponseInterface $response,
    ) {
        $this->setResponse($this->response);
    }

    public function effectiveUri(): string
    {
        return empty($this->redirects) ? $this->requestedUri() : end($this->redirects);
    }

    public function requestedUri(): string
    {
        return $this->request->getUri();
    }

    public function isRedirect(): bool
    {
        return $this->response->getStatusCode() >= 300 && $this->response->getStatusCode() < 400;
    }

    public function redirects(): array
    {
        return $this->redirects;
    }

    public function setResponse(ResponseInterface $response): void
    {
        $this->response = $response;

        if ($this->isRedirect()) {
            $this->addRedirectUri();
        }
    }

    private function addRedirectUri(): void
    {
        $this->redirects[] = Url::parse($this->effectiveUri())->resolve($this->response->getHeaderLine('Location'));
    }
}
