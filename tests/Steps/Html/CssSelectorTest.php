<?php

namespace tests\Steps\Html;

use Crwlr\Crawler\Steps\Html\CssSelector;
use Symfony\Component\DomCrawler\Crawler;

use function tests\helper_getSimpleListHtml;

test('The apply method returns a string for a single match', function () {
    $html = '<div class="item">test</div>';

    $domCrawler = new Crawler($html);

    expect((new CssSelector('.item'))->apply($domCrawler))->toBe('test');
});

test('The apply method returns an array of strings for multiple matches', function () {
    $html = '<div class="item">test</div><div class="item">test 2 <span>sub</span></div><div class="item">test 3</div>';

    $domCrawler = new Crawler($html);

    expect((new CssSelector('.item'))->apply($domCrawler))->toBe(['test', 'test 2 sub', 'test 3']);
});

test('The apply method returns null if nothing matches', function () {
    $html = '<div class="item">test</div>';

    $domCrawler = new Crawler($html);

    expect((new CssSelector('.aitem'))->apply($domCrawler))->toBeNull();
});

it('trims whitespace', function () {
    $html = <<<HTML
        <div class="item">
            test
        </div>
        HTML;

    $domCrawler = new Crawler($html);

    expect((new CssSelector('.item'))->apply($domCrawler))->toBe('test');
});

test('The filter method returns the filtered Symfony DOM Crawler instance', function () {
    $html = <<<HTML
        <div id="items">
            <div class="item" data-match="1">one</div>
            <div class="item" data-match="1">two</div>
            <div class="item">three</div>
        </div>
        HTML;

    $domCrawler = new Crawler($html);

    $filtered = (new CssSelector('#items .item[data-match=1]'))->filter($domCrawler);

    expect($filtered)->toBeInstanceOf(Crawler::class);

    expect($filtered->count())->toBe(2);

    expect($filtered->first()->outerHtml())->toBe('<div class="item" data-match="1">one</div>');

    expect($filtered->last()->outerHtml())->toBe('<div class="item" data-match="1">two</div>');
});

it('contains inner tags when the html method is called', function () {
    $html = '<div class="item">test <span>sub</span></div>';

    $domCrawler = new Crawler($html);

    expect((new CssSelector('.item'))->html()->apply($domCrawler))->toBe('test <span>sub</span>');
});

it('contains also the outer tag when the outerHtml method is called', function () {
    $html = '<div class="item">test <span>sub</span></div>';

    $domCrawler = new Crawler($html);

    expect((new CssSelector('.item'))->outerHtml()->apply($domCrawler))
        ->toBe('<div class="item">test <span>sub</span></div>');
});

it('does not contain text of children when innerText is called', function () {
    $html = '<div class="item">test <span>sub</span></div>';

    $domCrawler = new Crawler($html);

    expect((new CssSelector('.item'))->innerText()->apply($domCrawler))->toBe('test');
});

it('gets the contents of an attribute using the attribute method', function () {
    $html = '<div class="item" data-attr="content">test</div>';

    $domCrawler = new Crawler($html);

    expect((new CssSelector('.item'))->attribute('data-attr')->apply($domCrawler))->toBe('content');
});

it('turns the value into an absolute url when toAbsoluteUrl() is called', function () {
    $html = '<a href="/packages/crawler/v0.4/getting-started">getting started</a>';

    $domCrawler = new Crawler($html);

    $selector = new CssSelector('a');

    $selector->setBaseUrl('https://www.crwlr.software/')
        ->attribute('href');

    expect($selector->apply($domCrawler))->toBe('/packages/crawler/v0.4/getting-started');

    $selector->toAbsoluteUrl();

    expect($selector->apply($domCrawler))->toBe('https://www.crwlr.software/packages/crawler/v0.4/getting-started');
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

        $domCrawler = new Crawler($html);

        $selector = new CssSelector('a');

        $selector->setBaseUrl('https://www.example.com/a/b')
            ->attribute('href');

        expect($selector->apply($domCrawler))->toBe('e');

        $selector->toAbsoluteUrl();

        expect($selector->apply($domCrawler))->toBe('https://www.example.com/c/e');
    }
);

it('gets an absolute link from the href attribute of a link element, when the link() method is called', function () {
    $html = '<div id="foo"><a class="bar" href="/foo/bar">Foo</a></div>';

    $domCrawler = new Crawler($html);

    $selector = new CssSelector('#foo .bar');

    $selector->setBaseUrl('https://www.example.com/');

    expect($selector->apply($domCrawler))->toBe('Foo');

    $selector->link();

    expect($selector->apply($domCrawler))->toBe('https://www.example.com/foo/bar');
});

it('gets only the first matching element when the first() method is called', function () {
    $domCrawler = new Crawler(helper_getSimpleListHtml());

    $selector = (new CssSelector('#list .item'))->first();

    expect($selector->apply($domCrawler))->toBe('one');
});

it('gets only the last matching element when the last() method is called', function () {
    $domCrawler = new Crawler(helper_getSimpleListHtml());

    $selector = (new CssSelector('#list .item'))->last();

    expect($selector->apply($domCrawler))->toBe('four');
});

it('gets only the nth matching element when the nth() method is called', function () {
    $domCrawler = new Crawler(helper_getSimpleListHtml());

    $selector = (new CssSelector('#list .item'))->nth(3);

    expect($selector->apply($domCrawler))->toBe('three');
});

it('returns null when no nth matching element exists', function () {
    $domCrawler = new Crawler(helper_getSimpleListHtml());

    $selector = (new CssSelector('#list .item'))->nth(5);

    expect($selector->apply($domCrawler))->toBeNull();
});

it('gets only even matching elements when the even() method is called', function () {
    $domCrawler = new Crawler(helper_getSimpleListHtml());

    $selector = (new CssSelector('#list .item'))->even();

    expect($selector->apply($domCrawler))->toBe(['two', 'four']);
});

it('gets only odd matching elements when the odd() method is called', function () {
    $domCrawler = new Crawler(helper_getSimpleListHtml());

    $selector = (new CssSelector('#list .item'))->odd();

    expect($selector->apply($domCrawler))->toBe(['one', 'three']);
});
