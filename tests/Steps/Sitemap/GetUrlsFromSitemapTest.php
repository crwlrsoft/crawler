<?php

namespace tests\Steps\Sitemap;

use Crwlr\Crawler\Steps\Sitemap;

use function tests\helper_generatorToArray;
use function tests\helper_getFastLoader;
use function tests\helper_getHttpClientWithResponses;
use function tests\helper_getSitemapIndexXml;
use function tests\helper_getSitemapXml;
use function tests\helper_invokeStepWithInput;

it('gets all urls from a sitemap XML', function () {
    $xml = <<<XML
        <?xml version="1.0" encoding="UTF-8"?>
        <urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
        <url><loc>https://www.crwlr.software/</loc><priority>0.5</priority></url>
        <url><loc>https://www.crwlr.software/packages</loc><priority>0.7</priority></url>
        <url><loc>https://www.crwlr.software/blog</loc><priority>0.7</priority></url>
        <url><loc>https://www.crwlr.software/blog/whats-new-in-crwlr-crawler-v0-5</loc><priority>1</priority><lastmod>2022-09-03</lastmod></url>
        <url><loc>https://www.crwlr.software/blog/dealing-with-http-url-query-strings-in-php</loc><priority>1</priority><lastmod>2022-06-02</lastmod></url>
        <url><loc>https://www.crwlr.software/blog/whats-new-in-crwlr-crawler-v0-4</loc><priority>1</priority><lastmod>2022-05-10</lastmod></url>
        <url><loc>https://www.crwlr.software/blog/whats-new-in-crwlr-crawler-v0-2-and-v0-3</loc><priority>1</priority><lastmod>2022-04-30</lastmod></url>
        <url><loc>https://www.crwlr.software/blog/release-of-crwlr-crawler-v-0-1-0</loc><priority>1</priority><lastmod>2022-04-18</lastmod></url>
        <url><loc>https://www.crwlr.software/blog/prevent-homograph-attacks-in-user-input-urls</loc><priority>1</priority><lastmod>2022-01-19</lastmod></url>
        </urlset>
        XML;

    $outputs = helper_invokeStepWithInput(Sitemap::getUrlsFromSitemap(), $xml);

    expect($outputs)->toHaveCount(9)
        ->and($outputs[0]->get())->toBe('https://www.crwlr.software/')
        ->and($outputs[8]->get())->toBe('https://www.crwlr.software/blog/prevent-homograph-attacks-in-user-input-urls');
});

it('gets all urls with additional data when the withData() method is used', function () {
    $xml = <<<XML
        <?xml version="1.0" encoding="UTF-8"?>
        <urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
        <url><loc>https://www.crwlr.software/blog/whats-new-in-crwlr-crawler-v0-5</loc><priority>1</priority><lastmod>2022-09-03</lastmod></url>
        <url><loc>https://www.crwlr.software/blog/dealing-with-http-url-query-strings-in-php</loc><priority>1</priority><lastmod>2022-06-02</lastmod></url>
        <url><loc>https://www.crwlr.software/blog/whats-new-in-crwlr-crawler-v0-4</loc><priority>0.7</priority><lastmod>2022-05-10</lastmod></url>
        </urlset>
        XML;

    $outputs = helper_invokeStepWithInput(Sitemap::getUrlsFromSitemap()->withData(), $xml);

    expect($outputs)->toHaveCount(3)
        ->and($outputs[0]->get())->toBe([
            'url' => 'https://www.crwlr.software/blog/whats-new-in-crwlr-crawler-v0-5',
            'lastmod' => '2022-09-03',
            'priority' => '1',
        ])
        ->and($outputs[1]->get())->toBe([
            'url' => 'https://www.crwlr.software/blog/dealing-with-http-url-query-strings-in-php',
            'lastmod' => '2022-06-02',
            'priority' => '1',
        ])
        ->and($outputs[2]->get())->toBe([
            'url' => 'https://www.crwlr.software/blog/whats-new-in-crwlr-crawler-v0-4',
            'lastmod' => '2022-05-10',
            'priority' => '0.7',
        ]);
});

it('doesn\'t fail when sitemap is empty', function () {
    $xml = <<<XML
        <?xml version="1.0" encoding="UTF-8"?>
        <urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
        </urlset>
        XML;

    $outputs = helper_invokeStepWithInput(Sitemap::getUrlsFromSitemap()->withData(), $xml);

    expect($outputs)->toHaveCount(0);
});

it(
    'doesn\'t fail when the urlset tag contains attributes, that would cause the symfony DomCrawler to not find the ' .
    'elements',
    function () {
        $xml = <<<XML
            <?xml version="1.0" encoding="UTF-8"?>
            <urlset xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
                    xsi:schemaLocation="http://www.sitemaps.org/schemas/sitemap/0.9 http://www.sitemaps.org/schemas/sitemap/0.9/sitemap.xsd"
                    xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
                <url><loc>https://www.crwlr.software/blog/whats-new-in-crwlr-crawler-v0-5</loc></url>
                <url><loc>https://www.crwlr.software/blog/dealing-with-http-url-query-strings-in-php</loc></url>
                <url><loc>https://www.crwlr.software/blog/whats-new-in-crwlr-crawler-v0-4</loc></url>
            </urlset>
            XML;

        $outputs = helper_invokeStepWithInput(Sitemap::getUrlsFromSitemap(), $xml);

        expect($outputs)->toHaveCount(3);
    },
);

it(
    'doesn\'t fail when the urlset tag contains attributes, that would cause the symfony DomCrawler to not find the ' .
    'elements, when the XML content has no line breaks',
    function () {
        $xml = <<<XML
            <?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:news="http://www.google.com/schemas/sitemap-news/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml" xmlns:mobile="http://www.google.com/schemas/sitemap-mobile/1.0" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1" xmlns:video="http://www.google.com/schemas/sitemap-video/1.1"><url><loc>https://www.crwlr.software/blog/whats-new-in-crwlr-crawler-v0-5</loc></url><url><loc>https://www.crwlr.software/blog/dealing-with-http-url-query-strings-in-php</loc></url><url><loc>https://www.crwlr.software/blog/whats-new-in-crwlr-crawler-v0-4</loc></url></urlset>
            XML;

        $outputs = helper_invokeStepWithInput(Sitemap::getUrlsFromSitemap(), $xml);

        expect($outputs)->toHaveCount(3);
    },
);

it('loads the sitemaps referenced in a sitemap index (via the loader\'s SitemapIndexHandler) and gets all urls', function () {
    $requestCounts = [];

    $httpClient = helper_getHttpClientWithResponses([
        'https://www.example.com/sitemap_main.xml' => helper_getSitemapXml(
            'https://www.example.com/',
            'https://www.example.com/pricing',
        ),
        'https://www.example.com/docs/sitemap.xml' => helper_getSitemapXml(
            'https://www.example.com/docs',
            'https://www.example.com/docs/getting-started',
        ),
    ], $requestCounts);

    $step = Sitemap::getUrlsFromSitemap()->setLoader(helper_getFastLoader(httpClient: $httpClient));

    $outputs = helper_invokeStepWithInput(
        $step,
        helper_getSitemapIndexXml('https://www.example.com/sitemap_main.xml', 'https://www.example.com/docs/sitemap.xml'),
    );

    expect(array_map(fn($output) => $output->get(), $outputs))->toBe([
        'https://www.example.com/',
        'https://www.example.com/pricing',
        'https://www.example.com/docs',
        'https://www.example.com/docs/getting-started',
    ])
        ->and($requestCounts)->toBe([
            'https://www.example.com/sitemap_main.xml' => 1,
            'https://www.example.com/docs/sitemap.xml' => 1,
        ]);
});

it('gets all urls with additional data from the sitemaps referenced in a sitemap index', function () {
    $httpClient = helper_getHttpClientWithResponses([
        'https://www.example.com/sitemap1.xml' => helper_getSitemapXml('https://www.example.com/foo'),
    ]);

    $step = Sitemap::getUrlsFromSitemap()->withData()->withLoader(helper_getFastLoader(httpClient: $httpClient));

    $outputs = helper_invokeStepWithInput($step, helper_getSitemapIndexXml('https://www.example.com/sitemap1.xml'));

    expect($outputs)->toHaveCount(1)
        ->and($outputs[0]->get())->toBe(['url' => 'https://www.example.com/foo', 'lastmod' => '2024-01-01']);
});

it('doesn\'t fail when it gets a sitemap index but has no loader, but logs a warning', function () {
    $logger = \Mockery::mock(\Psr\Log\LoggerInterface::class);

    $logger->shouldReceive('warning')->once()->withArgs(function (string $message) {
        return str_contains($message, 'sitemap index') && str_contains($message, 'HttpLoader');
    });

    $step = Sitemap::getUrlsFromSitemap()->addLogger($logger);

    $outputs = helper_invokeStepWithInput($step, helper_getSitemapIndexXml('https://www.example.com/sitemap1.xml'));

    expect($outputs)->toHaveCount(0);
});

it('gets the loader from the crawler it is added to, so it can load sitemaps from a sitemap index', function () {
    $httpClient = helper_getHttpClientWithResponses([
        'https://www.example.com/sitemap.xml' => helper_getSitemapIndexXml('https://www.example.com/sitemap1.xml'),
        'https://www.example.com/sitemap1.xml' => helper_getSitemapXml(
            'https://www.example.com/foo',
            'https://www.example.com/bar',
        ),
    ]);

    $crawler = new class ($httpClient) extends \Crwlr\Crawler\HttpCrawler {
        public function __construct(private readonly \GuzzleHttp\Client $httpClient)
        {
            parent::__construct();
        }

        protected function userAgent(): \Crwlr\Crawler\UserAgents\UserAgentInterface
        {
            return new \Crwlr\Crawler\UserAgents\UserAgent('TestBot');
        }

        protected function loader(
            \Crwlr\Crawler\UserAgents\UserAgentInterface $userAgent,
            \Psr\Log\LoggerInterface $logger,
        ): \Crwlr\Crawler\Loader\LoaderInterface {
            return helper_getFastLoader($userAgent, $logger, $this->httpClient);
        }
    };

    $crawler
        ->input('https://www.example.com/sitemap.xml')
        ->addStep(\Crwlr\Crawler\Steps\Loading\Http::get())
        ->addStep(Sitemap::getUrlsFromSitemap()->keepAs('url'));

    $results = helper_generatorToArray($crawler->run());

    expect(array_map(fn($result) => $result->get('url'), $results))->toBe([
        'https://www.example.com/foo',
        'https://www.example.com/bar',
    ]);
});
