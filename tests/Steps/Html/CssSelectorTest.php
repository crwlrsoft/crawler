<?php

namespace tests\Steps\Html;

use Crwlr\Crawler\Steps\Html\CssSelector;
use Symfony\Component\DomCrawler\Crawler;

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
