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

if ($route === '/crawling/main') {
    echo <<<HTML
        <!doctype html>
        <html lang="en">
        <body>
            <a href="/crawling/sub1">Subpage 1</a> <br>
            <a href="/crawling/sub2">Subpage 2</a> <br>
            
            <a href="https://www.crwlr.software/packages/crawler">External link</a>
        </body>
        </html>
        HTML;
}

if ($route === '/crawling/sub1') {
    echo <<<HTML
        <!doctype html>
        <html lang="en">
        <body>
            <a href="/crawling/sub1/sub1">Subpage 1 of Subpage 1</a> <br>
            
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
