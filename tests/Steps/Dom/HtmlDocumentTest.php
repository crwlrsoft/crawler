<?php

namespace tests\Steps\Dom;

use Crwlr\Crawler\Steps\Dom\HtmlDocument;
use Crwlr\Crawler\Steps\Dom\HtmlElement;
use Crwlr\Crawler\Steps\Dom\NodeList;

it('gets the href of a base tag in the document', function () {
    $html = '<html><head><title>foo</title><base href="/foo/bar" /></head><body>hello</body></html>';

    $document = new HtmlDocument($html);

    expect($document->getBaseHref())->toBe('/foo/bar');
});

it('gets the href of the first base tag in the document', function () {
    $html = <<<HTML
        <html>
        <head>
            <title>foo</title>
            <base href="/foo" />
            <base href="/bar" />
        </head>
        <body>hey</body>
        </html>
        HTML;

    $document = new HtmlDocument($html);

    expect($document->getBaseHref())->toBe('/foo');
});

test('getBaseHref() returns null if the document does not contain a base tag', function () {
    $html = '<html><head><title>foo</title></head><body>hey</body></html>';

    $document = new HtmlDocument($html);

    expect($document->getBaseHref())->toBeNull();
});

test('the querySelector() method returns an HtmlElement object', function () {
    $html = '<html><head><title>foo</title></head><body><div class="element">hello</div></body></html>';

    $document = new HtmlDocument($html);

    expect($document->querySelector('.element'))->toBeInstanceOf(HtmlElement::class);
});

test('the querySelectorAll() method returns a NodeList of HtmlElement objects', function () {
    $html = '<html><head><title>foo</title></head><body><ul><li>foo</li><li>bar</li></ul></body></html>';

    $document = new HtmlDocument($html);

    $nodeList = $document->querySelectorAll('ul li');

    expect($nodeList)->toBeInstanceOf(NodeList::class);

    $anyNodesChecked = false;

    foreach ($nodeList as $node) {
        expect($node)->toBeInstanceOf(HtmlElement::class);

        $anyNodesChecked = true;
    }

    expect($anyNodesChecked)->toBeTrue();
});

test('the queryXPath() method returns a NodeList of HtmlElement objects', function () {
    $html = '<html><head><title>foo</title></head><body><ul><li>foo</li><li>bar</li></ul></body></html>';

    $document = new HtmlDocument($html);

    $nodeList = $document->queryXPath('//ul/li');

    expect($nodeList)->toBeInstanceOf(NodeList::class);

    $anyNodesChecked = false;

    foreach ($nodeList as $node) {
        expect($node)->toBeInstanceOf(HtmlElement::class);

        $anyNodesChecked = true;
    }

    expect($anyNodesChecked)->toBeTrue();
});
