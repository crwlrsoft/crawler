<?php

namespace Crwlr\Crawler\Steps\Loading;

use Closure;
use Crwlr\Crawler\Loader\Http\Messages\RespondedRequest;
use Crwlr\Crawler\Steps\Dom\HtmlElement;
use Crwlr\Crawler\Steps\Dom\XmlDocument;
use Crwlr\Crawler\Steps\Html\GetLink;
use Crwlr\Crawler\Steps\Loading\Http\Document;
use Crwlr\Crawler\Steps\Sitemap\GetUrlsFromSitemap;
use Crwlr\Utils\PhpVersion;
use Crwlr\Url\Url;
use Exception;
use Generator;
use Psr\Http\Message\UriInterface;
use Throwable;

class HttpCrawl extends Http
{
    protected ?int $depth = null;

    protected bool $sameHost = true;

    protected string $host = '';

    protected bool $sameDomain = false;

    protected string $domain = '';

    protected ?string $pathStartsWith = null;

    protected ?string $pathRegex = null;

    protected ?Closure $customClosure = null;

    protected bool $inputIsSitemap = false;

    protected bool $loadAll = false;

    protected bool $keepUrlFragment = false;

    protected bool $useCanonicalLinks = false;

    /**
     * @var array<string,array<string,bool>>
     */
    protected array $urls = [];

    /**
     * @var array<string,true>
     */
    protected array $loadedUrls = [];

    protected int $yieldedResponseCount = 0;

    public function __construct(array $headers = [], string $httpVersion = '1.1')
    {
        parent::__construct(headers: $headers, httpVersion: $httpVersion);
    }

    public function depth(int $depth): static
    {
        $this->depth = $depth;

        return $this;
    }

    public function sameHost(): static
    {
        $this->sameHost = true;

        $this->sameDomain = false;

        return $this;
    }

    public function sameDomain(): static
    {
        $this->sameDomain = true;

        $this->sameHost = false;

        return $this;
    }

    public function pathStartsWith(string $startsWith = ''): static
    {
        $this->pathStartsWith = $startsWith;

        return $this;
    }

    public function pathMatches(string $regexPattern = ''): static
    {
        $this->pathRegex = $regexPattern;

        return $this;
    }

    public function customFilter(Closure $closure): static
    {
        $this->customClosure = $closure;

        return $this;
    }

    public function inputIsSitemap(): static
    {
        $this->inputIsSitemap = true;

        return $this;
    }

    public function loadAllButYieldOnlyMatching(): static
    {
        $this->loadAll = true;

        return $this;
    }

    public function keepUrlFragment(): static
    {
        $this->keepUrlFragment = true;

        return $this;
    }

    public function useCanonicalLinks(): static
    {
        $this->useCanonicalLinks = true;

        return $this;
    }

    protected function validateAndSanitizeInput(mixed $input): mixed
    {
        return $this->validateAndSanitizeToUriInterface($input);
    }

    /**
     * @param UriInterface $input
     * @throws Exception
     */
    protected function invoke(mixed $input): Generator
    {
        $this->setHostOrDomain($input);

        $response = $this->getResponseFromInputUri($input);

        if (!$response) {
            return;
        }

        $initialResponseDocument = new Document($response);

        $this->setResponseCanonicalUrl($response, $initialResponseDocument);

        $this->addLoadedUrlsFromResponse($response);

        if (!$this->inputIsSitemap && $this->matchesAllCriteria(Url::parse($input))) {
            $this->yieldedResponseCount++;

            yield $response;
        }

        $this->urls = $this->getUrlsFromInitialResponse($response, $initialResponseDocument);

        $depth = 1;

        while (
            !$this->depthIsExceeded($depth) &&
            !empty($this->urls) &&
            (!$this->maxOutputs || $this->yieldedResponseCount < $this->maxOutputs)
        ) {
            yield from $this->loadUrls();

            $depth++;
        }
    }

    /**
     * @throws Exception
     */
    protected function setHostOrDomain(UriInterface $uri): void
    {
        if ($this->sameHost) {
            $this->host = $uri->getHost();
        } else {
            $domain = Url::parse($uri)->domain();

            if (!is_string($domain) || empty($domain)) {
                throw new Exception('No domain in input url');
            }

            $this->domain = $domain;
        }
    }

    /**
     * @throws Exception
     */
    protected function loadUrls(): Generator
    {
        $newUrls = [];

        foreach ($this->urls as $url => $yieldResponse) {
            $uri = Url::parsePsr7($url);

            $response = $this->getResponseFromInputUri($uri);

            if ($response !== null && !$this->wasAlreadyLoaded($response)) {
                $document = new Document($response, $this->logger);

                $this->setResponseCanonicalUrl($response, $document);

                $yieldResponse = $this->yieldResponse($document, $yieldResponse['yield']);

                $this->addLoadedUrlsFromResponse($response);

                $newUrls = array_merge($newUrls, $this->getUrlsFromHtmlDocument($document));

                if ($yieldResponse) {
                    yield $response;

                    $this->yieldedResponseCount++;

                    if ($this->maxOutputs && $this->yieldedResponseCount >= $this->maxOutputs) {
                        break;
                    }
                }
            }
        }

        $this->urls = $newUrls;
    }

    /**
     * @return array<string,array<string,bool>>
     * @throws Exception
     */
    protected function getUrlsFromInitialResponse(RespondedRequest $respondedRequest, ?Document $document = null): array
    {
        if ($this->inputIsSitemap) {
            return $this->getUrlsFromSitemap($respondedRequest);
        } else {
            $document = $document ?? new Document($respondedRequest);

            return $this->getUrlsFromHtmlDocument($document);
        }
    }

    /**
     * @return array<string,array<string,bool>>
     * @throws Exception
     */
    protected function getUrlsFromSitemap(RespondedRequest $respondedRequest): array
    {
        $document = new XmlDocument(Http::getBodyString($respondedRequest));

        if (PhpVersion::isBelow(8, 4)) {
            $document = GetUrlsFromSitemap::fixUrlSetTag($document);
        }

        $urls = [];

        foreach ($document->querySelectorAll('urlset url loc') as $url) {
            $url = $this->handleUrlFragment(Url::parse($url->text()));

            if (!$this->isOnSameHostOrDomain($url)) {
                continue;
            }

            $matchesCriteria = $this->matchesCriteriaBesidesHostOrDomain($url);

            if (!$matchesCriteria && !$this->loadAll) {
                continue;
            }

            $url = $url->toString();

            if (!isset($urls[$url]) && !isset($this->urls[$url]) && !isset($this->loadedUrls[$url])) {
                $urls[$url] = ['yield' => $matchesCriteria];
            }
        }

        return $urls;
    }

    /**
     * @return array<string,array<string,bool>>
     * @throws Exception
     */
    protected function getUrlsFromHtmlDocument(Document $document): array
    {
        $this->addCanonicalUrlToLoadedUrls($document);

        $urls = [];

        foreach ($document->dom()->querySelectorAll('a') as $link) {
            if (GetLink::isSpecialNonHttpLink($link)) {
                continue;
            }

            try {
                $url = $this->handleUrlFragment($document->baseUrl()->resolve($link->getAttribute('href') ?? ''));
            } catch (Throwable) {
                $this->logger?->warning('Failed to resolve a link with href: ' . $link->getAttribute('href'));

                continue;
            }

            if (!$this->isOnSameHostOrDomain($url)) {
                continue;
            }

            $matchesCriteria = $this->matchesCriteriaBesidesHostOrDomain($url, $link);

            if (!$matchesCriteria && !$this->loadAll) {
                continue;
            }

            $url = $url->toString();

            if (!isset($urls[$url]) && !isset($this->urls[$url]) && !isset($this->loadedUrls[$url])) {
                $urls[$url] = ['yield' => $matchesCriteria];
            }
        }

        return $urls;
    }

    protected function addLoadedUrlsFromResponse(RespondedRequest $respondedRequest): void
    {
        $loadedUrls = [$respondedRequest->requestedUri() => true];

        foreach ($respondedRequest->redirects() as $redirectUrl) {
            $loadedUrls[$redirectUrl] = true;
        }

        foreach ($loadedUrls as $loadedUrl => $true) {
            if (!isset($this->loadedUrls[$loadedUrl])) {
                $this->loadedUrls[$loadedUrl] = true;
            }
        }
    }

    /**
     * If the loaded response had a redirect, it can be that it was a redirect to a page that was already loaded before.
     * In that case, don't yield that response again.
     *
     * @param RespondedRequest $respondedRequest
     * @return bool
     */
    protected function wasAlreadyLoaded(RespondedRequest $respondedRequest): bool
    {
        if (
            array_key_exists($respondedRequest->requestedUri(), $this->loadedUrls) ||
            array_key_exists($respondedRequest->effectiveUri(), $this->loadedUrls)
        ) {
            $this->logger?->info('Was already loaded before. Do not process this page again.');

            return true;
        }

        foreach ($respondedRequest->redirects() as $url) {
            if (array_key_exists($url, $this->loadedUrls)) {
                $this->logger?->info('Was already loaded before. Do not process this page again.');

                return true;
            }
        }

        return false;
    }

    protected function addCanonicalUrlToLoadedUrls(Document $document): void
    {
        if ($this->useCanonicalLinks && !isset($this->loadedUrls[$document->canonicalUrl()])) {
            $this->loadedUrls[$document->canonicalUrl()] = true;
        }
    }

    /**
     * Yield response only if the URL matches the defined criteria and if the canonical URL isn't already among the
     * loaded URLs (and of course, the user decided that canonical links shall be used, because this is optional).
     */
    protected function yieldResponse(Document $document, bool $urlMatchesCriteria): bool
    {
        if (!$urlMatchesCriteria) {
            return false;
        }

        return !$this->useCanonicalLinks || !array_key_exists($document->canonicalUrl(), $this->loadedUrls);
    }

    /**
     * @throws Exception
     */
    protected function setResponseCanonicalUrl(RespondedRequest $respondedRequest, Document $document): void
    {
        if ($this->useCanonicalLinks && $respondedRequest->effectiveUri() !== $document->canonicalUrl()) {
            $this->logger?->info('Canonical link URL of this document is: ' . $document->canonicalUrl());

            $respondedRequest->addRedirectUri($document->canonicalUrl());
        }
    }

    protected function depthIsExceeded(int $depth): bool
    {
        return $this->depth !== null && $depth > $this->depth;
    }

    /**
     * @throws Exception
     */
    protected function matchesAllCriteria(Url $url, ?HtmlElement $linkElement = null): bool
    {
        return $this->isOnSameHostOrDomain($url) && $this->matchesCriteriaBesidesHostOrDomain($url, $linkElement);
    }

    /**
     * @throws Exception
     */
    protected function matchesCriteriaBesidesHostOrDomain(Url $url, ?HtmlElement $linkElement = null): bool
    {
        return $this->matchesPathCriteria($url) &&
            $this->matchesCustomCriteria($url, $linkElement);
    }

    /**
     * @throws Exception
     */
    protected function isOnSameHostOrDomain(Url $url): bool
    {
        if ($this->sameHost) {
            return $this->host === $url->host();
        } else {
            return $this->domain === $url->domain();
        }
    }

    /**
     * @throws Exception
     */
    protected function matchesPathCriteria(Url $url): bool
    {
        if ($this->pathStartsWith === null && $this->pathRegex === null) {
            return true;
        }

        $path = $url->path() ?? '';

        return ($this->pathStartsWith === null || str_starts_with($path, $this->pathStartsWith)) &&
            ($this->pathRegex === null || preg_match($this->pathRegex, $path) === 1);
    }

    protected function matchesCustomCriteria(Url $url, ?HtmlElement $linkElement): bool
    {
        return $this->customClosure === null || $this->customClosure->call($this, $url, $linkElement);
    }

    /**
     * @throws Exception
     */
    protected function handleUrlFragment(Url $url): Url
    {
        if (!$this->keepUrlFragment) {
            $url->fragment('');
        }

        return $url;
    }
}
