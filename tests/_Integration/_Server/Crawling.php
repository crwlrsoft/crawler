<?php

/**
 * Structure:
 *
 * /crawling/main
 *  => /crawling/sub1
 *      => /crawling/sub1/sub1
 *  => /crawling/sub2
 *      => /crawling/sub2/sub1
 *          => /crawling/sub2/sub1/sub1
 */

if ($route === '/crawling/sitemap.xml') {
    echo <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
<url><loc>http://www.example.com/crawling/main</loc></url>
<url><loc>http://www.example.com/crawling/sub1</loc></url>
<url><loc>http://www.example.com/crawling/sub1/sub1</loc></url>
<url><loc>http://www.example.com/crawling/sub2</loc></url>
<url><loc>http://www.example.com/crawling/sub2/sub1</loc></url>
<url><loc>http://www.example.com/crawling/sub2/sub1/sub1</loc></url>
</urlset>
XML;
}

if ($route === '/crawling/sitemap2.xml') {
    echo <<<XML
<?xml version="1.0" encoding="UTF-8"?><?xml-stylesheet type="text/xsl" href="/typo3/sysext/seo/Resources/Public/CSS/Sitemap.xsl"?>
<urlset xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1" xsi:schemaLocation="http://www.sitemaps.org/schemas/sitemap/0.9 http://www.sitemaps.org/schemas/sitemap/0.9/sitemap.xsd http://www.google.com/schemas/sitemap-image/1.1 http://www.google.com/schemas/sitemap-image/1.1/sitemap-image.xsd" xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
<url><loc>http://www.example.com/crawling/main</loc></url>
<url><loc>http://www.example.com/crawling/sub1</loc></url>
<url><loc>http://www.example.com/crawling/sub1/sub1</loc></url>
<url><loc>http://www.example.com/crawling/sub2</loc></url>
<url><loc>http://www.example.com/crawling/sub2/sub1</loc></url>
<url><loc>http://www.example.com/crawling/sub2/sub1/sub1</loc></url>
</urlset>
XML;
}

if ($route === '/crawling/main') {
    echo <<<HTML
        <!doctype html>
        <html lang="en">
        <body>
            <a href="/crawling/sub1">Subpage 1</a> <br>
            <a href="/crawling/sub2">Subpage 2</a> <br>
            <a href="/crawling/sub2#fragment1">Subpage 2 - Fragment 1</a> <br>
            <a href="/crawling/sub2#fragment2">Subpage 2 - Fragment 2</a> <br>

            <a href="https://www.crwlr.software/packages/crawler">External link</a>

            <a href="mailto:somebody@example.com">mailto link</a>
            <a href="javascript:alert('hello');">javascript link</a>
            <a href="tel:+499123456789">phone link</a>

            <a href="//">broken link</a>
        </body>
        </html>
        HTML;
}

if ($route === '/crawling/sub1') {
    echo <<<HTML
        <!doctype html>
        <html lang="en">
        <head>
            <title>foo</title>
            <base href="/crawling/">
            <link rel="canonical" href="/crawling/sub1/sub1" />
        </head>
        <body>
            <a href="sub1/sub1">Subpage 1 of Subpage 1</a> <br>

            <a href="https://www.foo.com">External link</a>

            <a href="http://foo.example.com/crawling/main-on-subdomain">Link to subdomain</a>
        </body>
        </html>
        HTML;
}

if ($route === '/crawling/sub1/sub1') {
    echo <<<HTML
        <!doctype html>
        <html lang="en">
        <body>
            <h1>Final level of sub1</h1>
            <h2>Subpage 1 of Subpage 1</h2>
            <a href="/crawling/main">Back to main</a>
        </body>
        </html>
        HTML;
}

if ($route === '/crawling/sub2') {
    echo <<<HTML
        <!doctype html>
        <html lang="en">
        <body>
            <a href="/crawling/sub2/sub1">Subpage 1 of Subpage 2</a>
        </body>
        </html>
        HTML;
}

if ($route === '/crawling/sub2/sub1') {
    echo <<<HTML
        <!doctype html>
        <html lang="en">
        <head>
            <title>foo</title>
            <link rel="canonical" href="/crawling/sub1/sub1" />
        </head>
        <body>
            <a href="/crawling/sub2/sub1/sub1">Subpage 1 of Subpage 1 of Subpage 2</a>
        </body>
        </html>
        HTML;
}

if ($route === '/crawling/sub2/sub1/sub1') {
    echo <<<HTML
        <!doctype html>
        <html lang="en">
        <body>
            <h1>Final level of sub2</h1>
            <h2>Subpage 1 of Subpage 1 of Subpage 2</h2>
            <a href="/crawling/sub2">Back to Subpage 2</a>
        </body>
        </html>
        HTML;
}

if ($route === '/crawling/main-on-subdomain') {
    echo <<<HTML
        <!doctype html>
        <html lang="en">
        <body>
            <h1>Main page on subdomain</h1>
        </body>
        </html>
        HTML;
}
