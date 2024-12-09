<?php

namespace Tests\Steps\Dom;

use Crwlr\Crawler\Steps\Dom\HtmlDocument;
use Crwlr\Crawler\Steps\Dom\HtmlElement;
use Crwlr\Crawler\Steps\Dom\Node;
use Crwlr\Crawler\Steps\Dom\NodeList;
use DOMNode;
use Symfony\Component\DomCrawler\Crawler;

it('can be constructed from a symfony Crawler instance', function () {
    $html = <<<HTML
        <!doctype html>
        <html>
        <head></head>
        <body>
            <ul><li>foo</li><li>bar</li><li>baz</li></ul>
        </body>
        </html>
        HTML;

    $crawler = new Crawler($html);

    $filtered = $crawler->filter('ul li');

    $nodeList = new NodeList(
        $filtered,
        function (object $node): HtmlElement {
            /** @var \Dom\Node|DOMNode|Crawler $node */
            return new HtmlElement($node);
        },
    );

    expect($nodeList->count())->toBe(3)
        ->and($nodeList->first()?->text())->toBe('foo')
        ->and($nodeList->nth(2)?->text())->toBe('bar')
        ->and($nodeList->last()?->text())->toBe('baz')
        ->and($nodeList->each(fn($node) => $node->text()))->toBe(['foo', 'bar', 'baz']);
});

it('can be constructed from a \Dom\NodeList instance', function () {
    $html = <<<HTML
        <!doctype html>
        <html>
        <head></head>
        <body>
            <ul><li>foo</li><li>bar</li><li>baz</li></ul>
        </body>
        </html>
        HTML;

    $document = \Dom\HTMLDocument::createFromString($html, LIBXML_NOERROR);

    $nodeList = new NodeList(
        $document->querySelectorAll('ul li'),
        function (object $node): HtmlElement {
            /** @var \Dom\Node|DOMNode|Crawler $node */
            return new HtmlElement($node);
        },
    );

    expect($nodeList->count())->toBe(3)
        ->and($nodeList->first()?->text())->toBe('foo')
        ->and($nodeList->nth(2)?->text())->toBe('bar')
        ->and($nodeList->last()?->text())->toBe('baz')
        ->and($nodeList->each(fn($node) => $node->text()))->toBe(['foo', 'bar', 'baz']);
})->group('php84');

it('can be instantiated from an array of Nodes (object instances from this library)', function () {
    $html = <<<HTML
        <!doctype html>
        <html>
        <head></head>
        <body>
            <div class="list">
                <div class="element">foo</div><div class="element">bar</div><div class="element">baz</div>
            </div>
        </body>
        </html>
        HTML;

    $document = new HtmlDocument($html);

    $array = [];

    foreach ($document->querySelectorAll('.list .element') as $node) {
        $array[] = $node;
    }

    $newNodeList = new NodeList($array);

    expect($newNodeList->count())->toBe(3)
        ->and($newNodeList->first()?->text())->toBe('foo')
        ->and($newNodeList->last()?->text())->toBe('baz')
        ->and($newNodeList->nth(2)?->text())->toBe('bar');
});

it('gets the count of the node list', function () {
    $html = <<<HTML
        <!doctype html>
        <html>
        <head>
            <title>Foo</title>
        </head>
        <body>
            <ul><li>foo</li><li>bar</li><li>baz</li></ul>
        </body>
        </html>
        HTML;

    $document = new HtmlDocument($html);

    expect($document->querySelectorAll('ul li')->count())->toBe(3);
});

it('can be iterated and the elements are instances of Crwlr\Crawler\Steps\Dom\Node', function () {
    $html = <<<HTML
        <!doctype html>
        <html>
        <head>
            <title>Foo</title>
        </head>
        <body>
            <ul><li>foo</li><li>bar</li><li>baz</li></ul>
        </body>
        </html>
        HTML;

    $document = new HtmlDocument($html);

    $iteratesAnyNodes = false;

    foreach ($document->querySelectorAll('ul li') as $node) {
        expect($node)->toBeInstanceOf(Node::class);

        $iteratesAnyNodes = true;
    }

    expect($iteratesAnyNodes)->toBeTrue();
});

it(
    'can be iterated with the each() method and return values are returned as an array from the each() call',
    function () {
        $html = <<<HTML
            <!doctype html>
            <html>
            <head></head>
            <body>
                <div class="list">
                    <div class="element">foo</div>
                    <div class="element">bar</div>
                    <div class="element">baz</div>
                    <div class="element">quz</div>
                </div>
            </body>
            </html>
            HTML;

        $document = new HtmlDocument($html);

        $result = $document->querySelectorAll('.list .element')->each(function ($node) {
            return $node->text() . ' check';
        });

        expect($result)->toBe([
            'foo check',
            'bar check',
            'baz check',
            'quz check',
        ]);
    },
);

test('an empty NodeList can be iterated', function () {
    $html = <<<HTML
        <!doctype html>
        <html>
        <head>
            <title>Foo</title>
        </head>
        <body>
            <ul><li>foo</li><li>bar</li><li>baz</li></ul>
        </body>
        </html>
        HTML;

    $document = new HtmlDocument($html);

    $iteratesAnyNodes = false;

    foreach ($document->querySelectorAll('ul lulu') as $node) {
        $iteratesAnyNodes = true;
    }

    expect($iteratesAnyNodes)->toBeFalse();
});

it('returns the first, last and nth element of the NodeList', function () {
    $html = <<<HTML
        <!doctype html>
        <html>
        <head></head>
        <body>
            <div class="list">
                <div class="element">foo</div>
                <div class="element">bar</div>
                <div class="element">baz</div>
                <div class="element">quz</div>
            </div>
        </body>
        </html>
        HTML;

    $document = new HtmlDocument($html);

    $list = $document->querySelectorAll('.list .element');

    expect($list->first())->toBeInstanceOf(HtmlElement::class)
        ->and($list->first()?->text())->toBe('foo')
        ->and($list->nth(2))->toBeInstanceOf(HtmlElement::class)
        ->and($list->nth(2)?->text())->toBe('bar')
        ->and($list->nth(3))->toBeInstanceOf(HtmlElement::class)
        ->and($list->nth(3)?->text())->toBe('baz')
        ->and($list->last())->toBeInstanceOf(HtmlElement::class)
        ->and($list->last()?->text())->toBe('quz');
});
