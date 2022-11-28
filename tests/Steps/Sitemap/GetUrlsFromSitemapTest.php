<?php

namespace Crwlr\Crawler\Steps\Sitemap;

use Crwlr\Crawler\Steps\Sitemap;

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

    expect($outputs)->toHaveCount(9);

    expect($outputs[0]->get())->toBe('https://www.crwlr.software/');

    expect($outputs[8]->get())->toBe('https://www.crwlr.software/blog/prevent-homograph-attacks-in-user-input-urls');
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

    expect($outputs)->toHaveCount(3);

    expect($outputs[0]->get())->toBe([
        'url' => 'https://www.crwlr.software/blog/whats-new-in-crwlr-crawler-v0-5',
        'lastmod' => '2022-09-03',
        'priority' => '1',
    ]);

    expect($outputs[1]->get())->toBe([
        'url' => 'https://www.crwlr.software/blog/dealing-with-http-url-query-strings-in-php',
        'lastmod' => '2022-06-02',
        'priority' => '1',
    ]);

    expect($outputs[2]->get())->toBe([
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
    }
);
