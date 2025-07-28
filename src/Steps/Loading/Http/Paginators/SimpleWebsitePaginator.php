<?php

namespace Crwlr\Crawler\Steps\Loading\Http\Paginators;

use Crwlr\Crawler\Loader\Http\Messages\RespondedRequest;
use Crwlr\Crawler\Steps\Dom;
use Crwlr\Crawler\Steps\Html\CssSelector;
use Crwlr\Crawler\Steps\Html\DomQuery;
use Crwlr\Crawler\Steps\Html\Exceptions\InvalidDomQueryException;
use Crwlr\Crawler\Steps\Loading\Http;
use Crwlr\Crawler\Utils\RequestKey;
use Crwlr\Url\Url;
use Exception;
use Psr\Http\Message\RequestInterface;
use Psr\Log\LoggerInterface;

class SimpleWebsitePaginator extends Http\AbstractPaginator
{
    /**
     * @var array<string, array{ url: string, foundOn: string }>
     */
    protected array $found = [];

    /**
     * @var array<string, true>
     */
    protected array $loadedUrls = [];

    protected DomQuery $paginationLinksSelector;

    protected string $latestRequestKey = '';

    /**
     * @var array<string, RequestInterface>
     */
    protected array $parentRequests = [];

    /**
     * @throws InvalidDomQueryException
     */
    public function __construct(string|DomQuery $paginationLinksSelector, int $maxPages = 1000)
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
        return $this->maxPagesReached() || empty($this->found) || $this->hasFinished;
    }

    public function getNextRequest(): ?RequestInterface
    {
        if (!$this->latestRequest) {
            return null;
        }

        $nextUrl = array_shift($this->found);

        if (!$nextUrl) {
            return null;
        }

        $request = $this->parentRequests[$nextUrl['foundOn']];

        $this->cleanUpParentRequests();

        return $request->withUri(Url::parsePsr7($nextUrl['url']));
    }

    /**
     * @throws Exception
     */
    public function processLoaded(
        RequestInterface $request,
        ?RespondedRequest $respondedRequest,
    ): void {
        $this->registerLoadedRequest($respondedRequest ?? $request);

        if ($this->latestRequest) {
            $this->latestRequestKey = RequestKey::from($this->latestRequest);
        }

        $this->loadedUrls[$request->getUri()->__toString()] = true;

        if ($respondedRequest) {
            foreach ($respondedRequest->redirects() as $redirectUrl) {
                $this->loadedUrls[$redirectUrl] = true;
            }

            $this->getPaginationLinksFromResponse($respondedRequest);
        }
    }

    public function logWhenFinished(LoggerInterface $logger): void
    {
        if ($this->maxPagesReached() && !empty($this->found)) {
            $logger->notice('Max pages limit reached');
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

        $document = new Dom\HtmlDocument($responseBody);

        $paginationLinksElements = $this->paginationLinksSelector instanceof CssSelector ?
            $document->querySelectorAll($this->paginationLinksSelector->query) :
            $document->queryXPath($this->paginationLinksSelector->query);

        foreach ($paginationLinksElements as $paginationLinksElement) {
            /** @var Dom\HtmlElement $paginationLinksElement */
            $this->addFoundUrlFromLinkElement(
                $paginationLinksElement,
                $document,
                $respondedRequest->effectiveUri(),
            );

            foreach ($paginationLinksElement->querySelectorAll('a') as $linkInPaginationLinksElement) {
                $this->addFoundUrlFromLinkElement(
                    $linkInPaginationLinksElement,
                    $document,
                    $respondedRequest->effectiveUri(),
                );
            }
        }
    }

    /**
     * @throws Exception
     */
    protected function addFoundUrlFromLinkElement(
        Dom\HtmlElement $linkElement,
        Dom\HtmlDocument $document,
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
        Dom\HtmlElement $linkElement,
        Dom\HtmlDocument $document,
        string $documentUrl,
    ): string {
        $baseUrl = Url::parse($documentUrl);

        $baseHref = $document->getBaseHref();

        if ($baseHref) {
            $baseUrl = $baseUrl->resolve($baseHref);
        }

        $linkHref = $linkElement->getAttribute('href') ?? '';

        return $baseUrl->resolve($linkHref)->__toString();
    }

    protected function isRelevantLinkElement(Dom\HtmlElement $element): bool
    {
        if ($element->nodeName() !== 'a') {
            return false;
        }

        $href = $element->getAttribute('href');

        return !empty($href) && !str_starts_with($href, '#');
    }

    protected function addFoundUrl(string $url): void
    {
        if (!isset($this->found[$url]) && !isset($this->loadedUrls[$url])) {
            if ($this->latestRequest && !array_key_exists($this->latestRequestKey, $this->parentRequests)) {
                $this->parentRequests[$this->latestRequestKey] = $this->latestRequest;
            }

            $this->found[$url] = ['url' => $url, 'foundOn' => $this->latestRequestKey];
        }
    }

    /**
     * The parent requests for found links are stored, so the new requests are always created from the actual parent,
     * not the latest registered response. After getting the next request to load, always check for all parent
     * requests, if there are still children in the found URLs. If not, the parent request can be forgotten, so we
     * keep memory usage as low as possible.
     */
    protected function cleanUpParentRequests(): void
    {
        foreach ($this->parentRequests as $requestKey => $request) {
            foreach ($this->found as $found) {
                if ($found['foundOn'] === $requestKey) {
                    continue 2;
                }
            }

            unset($this->parentRequests[$requestKey]);
        }
    }
}
