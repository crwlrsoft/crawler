<?php

namespace tests\Steps\Html;

use Crwlr\Crawler\Steps\Dom\HtmlDocument;
use Crwlr\Crawler\Steps\Html\CssSelector;
use Crwlr\Crawler\Steps\Html\Exceptions\InvalidDomQueryException;
use Crwlr\Html2Text\Html2Text;

use function tests\helper_getSimpleListHtml;

it('throws an exception when created with an invalid CSS Selector', function ($selector) {
    new CssSelector($selector);
})->throws(InvalidDomQueryException::class)->with(['.foo;', '.foo:before']);

test('The apply method returns a string for a single match', function () {
    $html = '<div class="item">test</div>';

    expect((new CssSelector('.item'))->apply(new HtmlDocument($html)))->toBe('test');
});

test('The apply method returns an array of strings for multiple matches', function () {
    $html = '<div class="item">test</div><div class="item">test 2 <span>sub</span></div><div class="item">test 3</div>';

    expect((new CssSelector('.item'))->apply(new HtmlDocument($html)))->toBe(['test', 'test 2 sub', 'test 3']);
});

test('The apply method returns null if nothing matches', function () {
    $html = '<div class="item">test</div>';

    expect((new CssSelector('.aitem'))->apply(new HtmlDocument($html)))->toBeNull();
});

it('trims whitespace', function () {
    $html = <<<HTML
        <div class="item">
            test
        </div>
        HTML;

    expect((new CssSelector('.item'))->apply(new HtmlDocument($html)))->toBe('test');
});

it('contains inner tags when the html method is called', function () {
    $html = '<div class="item">test <span>sub</span></div>';

    expect((new CssSelector('.item'))->html()->apply(new HtmlDocument($html)))->toBe('test <span>sub</span>');
});

it('contains also the outer tag when the outerHtml method is called', function () {
    $html = '<div class="item">test <span>sub</span></div>';

    expect((new CssSelector('.item'))->outerHtml()->apply(new HtmlDocument($html)))
        ->toBe('<div class="item">test <span>sub</span></div>');
});

it('returns formatted text when formattedText() is called', function () {
    $html = '<article id="a"><h1>headline</h1><p>paragraph</p><ul><li>item 1</li><li>item 2</li></ul></article>';

    expect((new CssSelector('#a'))->formattedText()->apply(new HtmlDocument($html)))
        ->toBe(<<<TEXT
        # headline

        paragraph

        * item 1
        * item 2
        TEXT);
});

test('you can provide your own converter instance to get formattedText()', function () {
    $html = '<article id="a"><h1>headline</h1><p>paragraph</p><ul><li>item 1</li><li>item 2</li></ul></article>';

    $converter = new Html2Text();

    $converter->removeConverter('ul');

    expect((new CssSelector('#a'))->formattedText($converter)->apply(new HtmlDocument($html)))
        ->toBe(<<<TEXT
        # headline

        paragraph

        item 1
        item 2
        TEXT);
});

it('gets the contents of an attribute using the attribute method', function () {
    $html = '<div class="item" data-attr="content">test</div>';

    expect((new CssSelector('.item'))->attribute('data-attr')->apply(new HtmlDocument($html)))->toBe('content');
});

it('turns the value into an absolute url when toAbsoluteUrl() is called', function () {
    $html = '<a href="/packages/crawler/v0.4/getting-started">getting started</a>';

    $document = new HtmlDocument($html);

    $selector = new CssSelector('a');

    $selector->setBaseUrl('https://www.crwlr.software/')
        ->attribute('href');

    expect($selector->apply($document))->toBe('/packages/crawler/v0.4/getting-started');

    $selector->toAbsoluteUrl();

    expect($selector->apply($document))->toBe('https://www.crwlr.software/packages/crawler/v0.4/getting-started');
});

it(
    'turns the value into the correct absolute url when toAbsoluteUrl() is called and the HTML contains a base tag',
    function () {
        $html = <<<HTML
            <!DOCTYPE html>
            <html>
            <head>
            <base href="/c/d" />
            </head>
            <body><a href="e">link</a></body>
            </html>
            HTML;

        $document = new HtmlDocument($html);

        $selector = new CssSelector('a');

        $selector->setBaseUrl('https://www.example.com/a/b')
            ->attribute('href');

        expect($selector->apply($document))->toBe('e');

        $selector->toAbsoluteUrl();

        expect($selector->apply($document))->toBe('https://www.example.com/c/e');
    },
);

it('gets an absolute link from the href attribute of a link element, when the link() method is called', function () {
    $html = '<div id="foo"><a class="bar" href="/foo/bar">Foo</a></div>';

    $document = new HtmlDocument($html);

    $selector = new CssSelector('#foo .bar');

    $selector->setBaseUrl('https://www.example.com/');

    expect($selector->apply($document))->toBe('Foo');

    $selector->link();

    expect($selector->apply($document))->toBe('https://www.example.com/foo/bar');
});

it('gets only the first matching element when the first() method is called', function () {
    $selector = (new CssSelector('#list .item'))->first();

    expect($selector->apply(new HtmlDocument(helper_getSimpleListHtml())))->toBe('one');
});

it('gets only the last matching element when the last() method is called', function () {
    $selector = (new CssSelector('#list .item'))->last();

    expect($selector->apply(new HtmlDocument(helper_getSimpleListHtml())))->toBe('four');
});

it('gets only the nth matching element when the nth() method is called', function () {
    $selector = (new CssSelector('#list .item'))->nth(3);

    expect($selector->apply(new HtmlDocument(helper_getSimpleListHtml())))->toBe('three');
});

it('returns null when no nth matching element exists', function () {
    $selector = (new CssSelector('#list .item'))->nth(5);

    expect($selector->apply(new HtmlDocument(helper_getSimpleListHtml())))->toBeNull();
});

it('gets only even matching elements when the even() method is called', function () {
    $selector = (new CssSelector('#list .item'))->even();

    expect($selector->apply(new HtmlDocument(helper_getSimpleListHtml())))->toBe(['two', 'four']);
});

it('gets only odd matching elements when the odd() method is called', function () {
    $selector = (new CssSelector('#list .item'))->odd();

    expect($selector->apply(new HtmlDocument(helper_getSimpleListHtml())))->toBe(['one', 'three']);
});
