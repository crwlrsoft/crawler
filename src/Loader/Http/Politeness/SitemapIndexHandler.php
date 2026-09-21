<?php

namespace Crwlr\Crawler\Loader\Http\Politeness;

use Crwlr\Crawler\Loader\Http\HttpLoader;
use Crwlr\Crawler\Loader\Http\Messages\RespondedRequest;
use Crwlr\Crawler\Loader\Loader;
use Crwlr\Crawler\Steps\Dom\XmlDocument;
use Crwlr\Crawler\Steps\Loading\Http;
use Crwlr\Utils\PhpVersion;
use Generator;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Resolves sitemap index files (https://www.sitemaps.org/protocol.html#index)
 */
class SitemapIndexHandler
{
    public function __construct(
        protected Loader $loader,
        protected ?LoggerInterface $logger = null,
    ) {}

    /**
     * Remove attributes from a sitemap index's <sitemapindex> tag
     *
     * Symfony's DomCrawler component has problems when the <sitemapindex> tag contains certain attributes.
     * So, if the count of sitemaps in the sitemap index is zero, try to remove all attributes from the tag.
     */
    public static function fixSitemapIndexTag(XmlDocument $dom): XmlDocument
    {
        if ($dom->querySelectorAll('sitemapindex sitemap')->count() === 0) {
            return new XmlDocument(
                preg_replace('/<sitemapindex.+?>/', '<sitemapindex>', $dom->outerXml()) ?? $dom->outerXml(),
            );
        }

        return $dom;
    }

    public static function isSitemapIndex(XmlDocument $document): bool
    {
        return self::getReferencedSitemapUrls($document) !== [];
    }

    /**
     * Get the URLs of all sitemaps referenced in a sitemap index document
     *
     * @return string[]
     */
    public static function getReferencedSitemapUrls(XmlDocument $document): array
    {
        if (PhpVersion::isBelow(8, 4)) {
            $document = self::fixSitemapIndexTag($document);
        }

        $urls = [];

        foreach ($document->querySelectorAll('sitemapindex sitemap') as $sitemapNode) {
            $url = trim($sitemapNode->querySelector('loc')?->text() ?? '');

            if ($url !== '' && !in_array($url, $urls, true)) {
                $urls[] = $url;
            }
        }

        return $urls;
    }

    /**
     * Load all the sitemaps referenced in a sitemap index document
     *
     * Nested sitemap indexes are resolved recursively, so this only yields actual sitemaps (<urlset> documents).
     *
     * @return Generator<XmlDocument>
     */
    public function getSitemaps(XmlDocument $sitemapIndex): Generator
    {
        $loadedSitemapUrls = [];

        foreach (self::getReferencedSitemapUrls($sitemapIndex) as $sitemapUrl) {
            yield from $this->loadSitemapRecursively($sitemapUrl, $loadedSitemapUrls);
        }
    }

    /**
     * @param string[] $loadedSitemapUrls
     * @return Generator<XmlDocument>
     */
    protected function loadSitemapRecursively(string $sitemapUrl, array &$loadedSitemapUrls): Generator
    {
        if (in_array($sitemapUrl, $loadedSitemapUrls, true)) {
            return;
        }

        $loadedSitemapUrls[] = $sitemapUrl;

        $sitemap = $this->loadSitemap($sitemapUrl);

        if (!$sitemap) {
            return;
        }

        $referencedSitemapUrls = self::getReferencedSitemapUrls($sitemap);

        if ($referencedSitemapUrls === []) {
            yield $sitemap;

            return;
        }

        foreach ($referencedSitemapUrls as $referencedSitemapUrl) {
            yield from $this->loadSitemapRecursively($referencedSitemapUrl, $loadedSitemapUrls);
        }
    }

    protected function loadSitemap(string $sitemapUrl): ?XmlDocument
    {
        $usedHeadlessBrowser = false;

        if ($this->loader instanceof HttpLoader) {
            // If loader is set to use headless browser, temporary switch to using PSR-18 HTTP Client.
            $usedHeadlessBrowser = $this->loader->usesHeadlessBrowser();

            $this->loader->useHttpClient();
        }

        try {
            $response = $this->loader->load($sitemapUrl);
        } catch (Throwable $exception) {
            $this->logger?->error('Failed to load sitemap ' . $sitemapUrl . ': ' . $exception->getMessage());

            $response = null;
        } finally {
            if ($this->loader instanceof HttpLoader && $usedHeadlessBrowser) {
                $this->loader->useHeadlessBrowser();
            }
        }

        if (!$response instanceof RespondedRequest || $response->response->getStatusCode() >= 400) {
            $this->logger?->warning('Failed to load sitemap ' . $sitemapUrl . ' referenced in sitemap index.');

            return null;
        }

        try {
            return new XmlDocument($this->removeUtf8Bom(Http::getBodyString($response)));
        } catch (Throwable $exception) {
            $this->logger?->error('Failed to parse sitemap ' . $sitemapUrl . ': ' . $exception->getMessage());

            return null;
        }
    }

    protected function removeUtf8Bom(string $string): string
    {
        if (substr($string, 0, 3) === (chr(239) . chr(187) . chr(191))) {
            return substr($string, 3);
        }

        return $string;
    }
}
