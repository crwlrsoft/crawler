<?php

namespace tests\Loader\Http\Politeness;

use Crwlr\Crawler\Loader\Http\Politeness\SitemapIndexHandler;
use Crwlr\Crawler\Steps\Dom\XmlDocument;
use Mockery;

use function tests\helper_generatorToArray;
use function tests\helper_getFastLoader;
use function tests\helper_getHttpClientWithResponses;
use function tests\helper_getSitemapIndexXml;
use function tests\helper_getSitemapXml;

/**
 * @param XmlDocument[] $sitemaps
 * @return string[]
 */
function helper_getUrlsFromSitemapDocuments(array $sitemaps): array
{
    $urls = [];

    foreach ($sitemaps as $sitemap) {
        foreach ($sitemap->querySelectorAll('urlset url loc') as $locNode) {
            $urls[] = $locNode->text();
        }
    }

    return $urls;
}

it('recognizes a sitemap index', function () {
    $sitemapIndex = new XmlDocument(helper_getSitemapIndexXml('https://www.example.com/sitemap1.xml'));

    $sitemap = new XmlDocument(helper_getSitemapXml('https://www.example.com/foo'));

    expect(SitemapIndexHandler::isSitemapIndex($sitemapIndex))->toBeTrue()
        ->and(SitemapIndexHandler::isSitemapIndex($sitemap))->toBeFalse();
});

it('gets the urls of the sitemaps referenced in a sitemap index', function () {
    $xml = <<<XML
        <?xml version="1.0" encoding="UTF-8"?>
        <sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
            <sitemap>
                <loc> https://www.example.com/sitemap1.xml </loc>
                <lastmod>2026-04-05T00:00:00+00:00</lastmod>
            </sitemap>
            <sitemap><loc>https://www.example.com/sitemap2.xml</loc></sitemap>
            <sitemap><loc>https://www.example.com/sitemap1.xml</loc></sitemap>
            <sitemap><loc></loc></sitemap>
            <sitemap></sitemap>
        </sitemapindex>
        XML;

    expect(SitemapIndexHandler::getReferencedSitemapUrls(new XmlDocument($xml)))->toBe([
        'https://www.example.com/sitemap1.xml',
        'https://www.example.com/sitemap2.xml',
    ]);
});

it(
    'gets the referenced sitemap urls when the sitemapindex tag contains attributes, that would cause the symfony ' .
    'DomCrawler to not find the elements',
    function () {
        $xml = <<<XML
            <?xml version="1.0" encoding="UTF-8"?>
            <sitemapindex xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
                    xsi:schemaLocation="http://www.sitemaps.org/schemas/sitemap/0.9 http://www.sitemaps.org/schemas/sitemap/0.9/siteindex.xsd"
                    xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
                <sitemap><loc>https://www.example.com/sitemap1.xml</loc></sitemap>
            </sitemapindex>
            XML;

        expect(SitemapIndexHandler::getReferencedSitemapUrls(new XmlDocument($xml)))
            ->toBe(['https://www.example.com/sitemap1.xml']);
    },
);

it('loads all the sitemaps referenced in a sitemap index', function () {
    $requestCounts = [];

    $httpClient = helper_getHttpClientWithResponses([
        'https://www.example.com/sitemap1.xml' => helper_getSitemapXml(
            'https://www.example.com/',
            'https://www.example.com/pricing',
        ),
        'https://www.example.com/sitemap2.xml' => helper_getSitemapXml('https://www.example.com/docs'),
    ], $requestCounts);

    $handler = new SitemapIndexHandler(helper_getFastLoader(httpClient: $httpClient));

    $sitemaps = helper_generatorToArray($handler->getSitemaps(new XmlDocument(helper_getSitemapIndexXml(
        'https://www.example.com/sitemap1.xml',
        'https://www.example.com/sitemap2.xml',
    ))));

    expect($sitemaps)->toHaveCount(2)
        ->and($sitemaps[0])->toBeInstanceOf(XmlDocument::class)
        ->and(helper_getUrlsFromSitemapDocuments($sitemaps))->toBe([
            'https://www.example.com/',
            'https://www.example.com/pricing',
            'https://www.example.com/docs',
        ])
        ->and($requestCounts)->toBe([
            'https://www.example.com/sitemap1.xml' => 1,
            'https://www.example.com/sitemap2.xml' => 1,
        ]);
});

it('resolves nested sitemap indexes and loads every sitemap only once', function () {
    $requestCounts = [];

    $httpClient = helper_getHttpClientWithResponses([
        // References the root index again (loop) and a nested index.
        'https://www.example.com/sitemap-a.xml' => helper_getSitemapIndexXml(
            'https://www.example.com/sitemap.xml',
            'https://www.example.com/sitemap-b.xml',
        ),
        // References a sitemap that is also referenced by the root index, and the index referencing itself.
        'https://www.example.com/sitemap-b.xml' => helper_getSitemapIndexXml(
            'https://www.example.com/sitemap-c.xml',
            'https://www.example.com/sitemap-a.xml',
        ),
        'https://www.example.com/sitemap-c.xml' => helper_getSitemapXml('https://www.example.com/c'),
        'https://www.example.com/sitemap.xml' => helper_getSitemapIndexXml('https://www.example.com/sitemap-a.xml'),
    ], $requestCounts);

    $handler = new SitemapIndexHandler(helper_getFastLoader(httpClient: $httpClient));

    $sitemaps = helper_generatorToArray($handler->getSitemaps(new XmlDocument(helper_getSitemapIndexXml(
        'https://www.example.com/sitemap-a.xml',
        'https://www.example.com/sitemap-c.xml',
    ))));

    expect(helper_getUrlsFromSitemapDocuments($sitemaps))->toBe(['https://www.example.com/c'])
        ->and($requestCounts)->toBe([
            'https://www.example.com/sitemap-a.xml' => 1,
            'https://www.example.com/sitemap.xml' => 1,
            'https://www.example.com/sitemap-b.xml' => 1,
            'https://www.example.com/sitemap-c.xml' => 1,
        ]);
});

it('skips referenced sitemaps that can\'t be loaded and logs a warning', function () {
    $httpClient = helper_getHttpClientWithResponses([
        'https://www.example.com/sitemap1.xml' => 500,
        'https://www.example.com/sitemap2.xml' => helper_getSitemapXml('https://www.example.com/foo'),
    ]);

    $logger = Mockery::mock(\Psr\Log\LoggerInterface::class)->shouldIgnoreMissing();

    $logger->shouldReceive('warning')->once()->withArgs(function (string $message) {
        return str_contains($message, 'https://www.example.com/sitemap1.xml');
    });

    $handler = new SitemapIndexHandler(helper_getFastLoader(httpClient: $httpClient), $logger);

    $sitemaps = helper_generatorToArray($handler->getSitemaps(new XmlDocument(helper_getSitemapIndexXml(
        'https://www.example.com/sitemap1.xml',
        'https://www.example.com/sitemap2.xml',
        'https://www.example.com/sitemap3.xml',
    ))));

    expect(helper_getUrlsFromSitemapDocuments($sitemaps))->toBe(['https://www.example.com/foo']);
});

it('temporarily switches the loader to the HTTP client when it is set to use the headless browser', function () {
    $requestCounts = [];

    $httpClient = helper_getHttpClientWithResponses([
        'https://www.example.com/sitemap1.xml' => helper_getSitemapXml('https://www.example.com/foo'),
    ], $requestCounts);

    $loader = helper_getFastLoader(httpClient: $httpClient);

    $loader->useHeadlessBrowser();

    $handler = new SitemapIndexHandler($loader);

    $sitemaps = helper_generatorToArray(
        $handler->getSitemaps(new XmlDocument(helper_getSitemapIndexXml('https://www.example.com/sitemap1.xml'))),
    );

    expect(helper_getUrlsFromSitemapDocuments($sitemaps))->toBe(['https://www.example.com/foo'])
        ->and($requestCounts)->toBe(['https://www.example.com/sitemap1.xml' => 1])
        ->and($loader->usesHeadlessBrowser())->toBeTrue();
});

it('is accessible via the HttpLoader and always returns the same instance', function () {
    $loader = helper_getFastLoader();

    expect($loader->sitemapIndex())->toBeInstanceOf(SitemapIndexHandler::class)
        ->and($loader->sitemapIndex())->toBe($loader->sitemapIndex());
});
