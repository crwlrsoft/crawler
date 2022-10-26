<?php

namespace Crwlr\Crawler\Steps\Loading\Http\Paginators;

use Crwlr\Crawler\Loader\Http\Messages\RespondedRequest;
use Crwlr\Crawler\Steps\Dom;
use Crwlr\Crawler\Steps\Html\DomQuery;
use Crwlr\Crawler\Steps\Html\DomQueryInterface;
use Crwlr\Crawler\Steps\Loading\Http;
use Crwlr\Url\Url;
use Exception;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\UriInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DomCrawler\Crawler;

class SimpleWebsitePaginator extends AbstractPaginator
{
    /**
     * @var array<string, string>
     */
    protected array $found = [];

    /**
     * @var array<string, true>
     */
    protected array $loaded = [];

    protected int $loadedPagesCount = 0;

    protected DomQueryInterface $paginationLinksSelector;

    public function __construct(string|DomQueryInterface $paginationLinksSelector, int $maxPages = 1000)
    {
        if (is_string($paginationLinksSelector)) {
            $this->paginationLinksSelector = Dom::cssSelector($paginationLinksSelector);
        } else {
            $this->paginationLinksSelector = $paginationLinksSelector;
        }

        parent::__construct($maxPages);
    }

    public function hasFinished(): bool
    {
        return $this->loadedPagesCount >= $this->maxPages || empty($this->found);
    }

    public function getNextUrl(): ?string
    {
        return array_shift($this->found);
    }

    /**
     * @throws Exception
     */
    public function processLoaded(
        UriInterface $url,
        RequestInterface $request,
        ?RespondedRequest $respondedRequest,
    ): void {
        $this->loaded[$url->__toString()] = true;

        $this->loadedPagesCount++;

        if ($respondedRequest) {
            foreach ($respondedRequest->redirects() as $redirectUrl) {
                $this->loaded[$redirectUrl] = true;
            }

            $this->getPaginationLinksFromResponse($respondedRequest);
        }
    }

    public function logWhenFinished(LoggerInterface $logger): void
    {
        if ($this->loadedPagesCount >= $this->maxPages && !empty($this->found)) {
            $logger->warning('Max pages limit reached');
        } else {
            $logger->info('All found pagination links loaded');
        }
    }

    /**
     * @throws Exception
     */
    protected function getPaginationLinksFromResponse(RespondedRequest $respondedRequest): void
    {
        $responseBody = Http::getBodyString($respondedRequest);

        $dom = new Crawler($responseBody);

        $paginationLinksElements = $this->paginationLinksSelector->filter($dom);

        foreach ($paginationLinksElements as $paginationLinksElement) {
            $paginationLinksElement = new Crawler($paginationLinksElement);

            $this->addFoundUrlFromLinkElement(
                $paginationLinksElement,
                $dom,
                $respondedRequest->effectiveUri(),
            );

            foreach ($paginationLinksElement->filter('a') as $linkInPaginationLinksElement) {
                $linkInPaginationLinksElement = new Crawler($linkInPaginationLinksElement);

                $this->addFoundUrlFromLinkElement(
                    $linkInPaginationLinksElement,
                    $dom,
                    $respondedRequest->effectiveUri(),
                );
            }
        }
    }

    /**
     * @throws Exception
     */
    protected function addFoundUrlFromLinkElement(
        Crawler $linkElement,
        Crawler $document,
        string $documentUrl,
    ): void {
        if ($this->isRelevantLinkElement($linkElement)) {
            $url = $this->getAbsoluteUrlFromLinkElement($linkElement, $document, $documentUrl);

            $this->addFoundUrl($url);
        }
    }

    /**
     * @throws Exception
     */
    protected function getAbsoluteUrlFromLinkElement(
        Crawler $linkElement,
        Crawler $document,
        string $documentUrl,
    ): string {
        $baseUrl = Url::parse($documentUrl);

        $baseHref = DomQuery::getBaseHrefFromDocument($document);

        if ($baseHref) {
            $baseUrl = $baseUrl->resolve($baseHref);
        }

        $linkHref = $linkElement->attr('href') ?? '';

        return $baseUrl->resolve($linkHref)->__toString();
    }

    protected function isRelevantLinkElement(Crawler $element): bool
    {
        if ($element->nodeName() !== 'a') {
            return false;
        }

        $href = $element->attr('href');

        return !empty($href) && !str_starts_with($href, '#');
    }

    protected function addFoundUrl(string $url): void
    {
        if (!isset($this->found[$url]) && !isset($this->loaded[$url])) {
            $this->found[$url] = $url;
        }
    }
}
