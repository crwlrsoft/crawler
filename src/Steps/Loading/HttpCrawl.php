<?php

namespace Crwlr\Crawler\Steps\Loading;

use Closure;
use Crwlr\Crawler\Loader\Http\Messages\RespondedRequest;
use Crwlr\Url\Url;
use Exception;
use Generator;
use Psr\Http\Message\UriInterface;
use Symfony\Component\DomCrawler\Crawler;

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

    /**
     * @var array<string,array<string,bool>>
     */
    protected array $urls = [];

    /**
     * @var array<string,true>
     */
    protected array $loadedUrls = [];

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

    protected function invoke(mixed $input): Generator
    {
        $this->setHostOrDomain($input);

        $response = $this->loader->load($this->getRequestFromInputUri($input));

        $this->addLoadedUrlsFromResponse($response);

        if ($response !== null) {
            if (!$this->inputIsSitemap && $this->matchesAllCriteria(Url::parse($input))) {
                yield $response;
            }

            $this->urls = $this->getUrlsFromInitialResponse($response);

            $depth = 1;

            while (!$this->depthIsExceeded($depth) && !empty($this->urls)) {
                yield from $this->loadUrls();

                $depth++;
            }
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

            $response = $this->loader->load($this->getRequestFromInputUri($uri));

            $this->addLoadedUrlsFromResponse($response);

            if ($response !== null) {
                if ($yieldResponse['yield'] === true) {
                    yield $response;
                }

                $newUrls = array_merge($newUrls, $this->getUrlsFromHtmlDocument($response));
            }
        }

        $this->urls = $newUrls;
    }

    /**
     * @return array<string,array<string,bool>>
     * @throws Exception
     */
    protected function getUrlsFromInitialResponse(RespondedRequest $respondedRequest): array
    {
        if ($this->inputIsSitemap) {
            return $this->getUrlsFromSitemap($respondedRequest);
        } else {
            return $this->getUrlsFromHtmlDocument($respondedRequest);
        }
    }

    /**
     * @return array<string,array<string,bool>>
     * @throws Exception
     */
    protected function getUrlsFromSitemap(RespondedRequest $respondedRequest): array
    {
        $domCrawler = new Crawler(Http::getBodyString($respondedRequest));

        $urls = [];

        foreach ($domCrawler->filter('urlset url loc') as $url) {
            $url = Url::parse($url->textContent);

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
    protected function getUrlsFromHtmlDocument(RespondedRequest $respondedRequest): array
    {
        $domCrawler = new Crawler(Http::getBodyString($respondedRequest));

        $baseUrl = Url::parse($respondedRequest->effectiveUri());

        $urls = [];

        foreach ($domCrawler->filter('a') as $link) {
            $linkElement = new Crawler($link);

            $url = $baseUrl->resolve($linkElement->attr('href') ?? '');

            if (!$this->isOnSameHostOrDomain($url)) {
                continue;
            }

            $matchesCriteria = $this->matchesCriteriaBesidesHostOrDomain($url, $linkElement);

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

    protected function depthIsExceeded(int $depth): bool
    {
        return $this->depth !== null && $depth > $this->depth;
    }

    /**
     * @throws Exception
     */
    protected function matchesAllCriteria(Url $url, ?Crawler $linkElement = null): bool
    {
        return $this->isOnSameHostOrDomain($url) && $this->matchesCriteriaBesidesHostOrDomain($url, $linkElement);
    }

    /**
     * @throws Exception
     */
    protected function matchesCriteriaBesidesHostOrDomain(Url $url, ?Crawler $linkElement = null): bool
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

    protected function matchesCustomCriteria(Url $url, ?Crawler $linkElement): bool
    {
        return $this->customClosure === null || $this->customClosure->call($this, $url, $linkElement);
    }
}
