<?php

namespace tests\Steps\Dom;

use Crwlr\Crawler\Steps\Dom\HtmlDocument;
use Crwlr\Crawler\Steps\Dom\HtmlElement;
use Crwlr\Crawler\Steps\Dom\NodeList;

test('child nodes selected via querySelector() are HtmlElement instances', function () {
    $html = <<<HTML
        <!doctype html>
        <html>
        <head></head>
        <body>
        <div id="wrapper"><div class="element"></div></div>
        </body>
        </html>
        HTML;

    $document = new HtmlDocument($html);

    $wrapperElement = $document->querySelector('#wrapper');

    expect($wrapperElement)->toBeInstanceOf(HtmlElement::class)
        ->and($wrapperElement?->querySelector('.element'))->toBeInstanceOf(HtmlElement::class);
});

test('child nodes selected via querySelectorAll() are HtmlElement instances', function () {
    $html = <<<HTML
        <!doctype html>
        <html>
        <head></head>
        <body>
        <div id="wrapper">
            <div class="element">foo</div>
            <div class="element">bar</div>
        </div>
        </body>
        </html>
        HTML;

    $document = new HtmlDocument($html);

    $wrapperElement = $document->querySelector('#wrapper');

    expect($wrapperElement)->toBeInstanceOf(HtmlElement::class);

    $childNodeList = $wrapperElement?->querySelectorAll('.element');

    expect($childNodeList)->toBeInstanceOf(NodeList::class)
        ->and($childNodeList?->count())->toBe(2)
        ->and($childNodeList?->first())->toBeInstanceOf(HtmlElement::class)
        ->and($childNodeList?->last())->toBeInstanceOf(HtmlElement::class);
});

test('child nodes selected via queryXPath() are HtmlElement instances', function () {
    $html = <<<HTML
        <!doctype html>
        <html>
        <head></head>
        <body>
        <div id="wrapper">
            <div class="element">foo</div>
            <div class="element">bar</div>
        </div>
        </body>
        </html>
        HTML;

    $document = new HtmlDocument($html);

    $wrapperElement = $document->queryXPath('//*[@id="wrapper"]')->first();

    expect($wrapperElement)->toBeInstanceOf(HtmlElement::class);

    $childNodeList = $wrapperElement?->queryXPath('//*[contains(@class, "element")]');

    expect($childNodeList)->toBeInstanceOf(NodeList::class)
        ->and($childNodeList?->count())->toBe(2)
        ->and($childNodeList?->first())->toBeInstanceOf(HtmlElement::class)
        ->and($childNodeList?->first()?->text())->toBe('foo')
        ->and($childNodeList?->last())->toBeInstanceOf(HtmlElement::class)
        ->and($childNodeList?->last()?->text())->toBe('bar');
});

it('gets the node name', function () {
    $html = <<<HTML
        <!doctype html>
        <html>
        <head></head>
        <body>
        <div class="element"><span class="child"></span></div>
        </body>
        </html>
        HTML;

    $document = new HtmlDocument($html);

    $node = $document->querySelector('.element');

    expect($node?->nodeName())->toBe('div')
        ->and($node?->querySelector('.child')?->nodeName())->toBe('span');
});

it('gets the text of a node', function () {
    $html = <<<HTML
        <!doctype html>
        <html>
        <head></head>
        <body>
        <div class="element">
            bli bla <span>blub</span>
        </div>
        </body>
        </html>
        HTML;

    $document = new HtmlDocument($html);

    $node = $document->querySelector('.element');

    expect($node?->text())->toBe('bli bla blub');
});

it('gets the outer HTML of a node', function () {
    $html = <<<HTML
        <!doctype html>
        <html>
        <head></head>
        <body>
        <div class="element">
            bli bla <span>blub</span>
        </div>
        </body>
        </html>
        HTML;

    $document = new HtmlDocument($html);

    $node = $document->querySelector('.element');

    expect($node?->outerHtml())->toBe(
        '<div class="element">' . PHP_EOL .
        '    bli bla <span>blub</span>' . PHP_EOL .
        '</div>',
    );
});

it('gets the inner HTML of a node', function () {
    $html = <<<HTML
        <!doctype html>
        <html>
        <head></head>
        <body>
        <div class="element">
            bli bla <span>blub</span>
        </div>
        </body>
        </html>
        HTML;

    $document = new HtmlDocument($html);

    $node = $document->querySelector('.element');

    expect($node?->innerHtml())->toBe(
        PHP_EOL .
        '    bli bla <span>blub</span>' . PHP_EOL,
    );
});

it('gets an attribute from a node', function () {
    $html = <<<HTML
        <!doctype html>
        <html>
        <head></head>
        <body>
        <a href="/foo/bar" class="element">Link</a>
        </body>
        </html>
        HTML;

    $document = new HtmlDocument($html);

    $node = $document->querySelector('.element');

    expect($node?->getAttribute('href'))->toBe('/foo/bar');
});
