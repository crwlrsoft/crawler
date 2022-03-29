<?php

namespace tests\Steps\Html;

use Crwlr\Crawler\Steps\Html\XPathQuery;
use Symfony\Component\DomCrawler\Crawler;

test('The apply method returns a string for a single match', function () {
    $xml = '<item>test</item>';

    $domCrawler = new Crawler($xml);

    expect((new XPathQuery('//item'))->apply($domCrawler))->toBe('test');
});

test('The apply method returns an array of strings for multiple matches', function () {
    $xml = '<item>test</item><item>test 2 <test>sub</test></item><item>test 3</item>';

    $domCrawler = new Crawler($xml);

    expect((new XPathQuery('//item'))->apply($domCrawler))->toBe(['test', 'test 2 sub', 'test 3']);
});

test('The apply method returns an empty string if nothing matches', function () {
    $xml = '<item>test</item>';

    $domCrawler = new Crawler($xml);

    expect((new XPathQuery('//aitem'))->apply($domCrawler))->toBe('');
});

it('trims whitespace', function () {
    $xml = <<<XML
        <item>
            test
        </item>
        XML;

    $domCrawler = new Crawler($xml);

    expect((new XPathQuery('//item'))->apply($domCrawler))->toBe('test');
});

test('The filter method returns the filtered Symfony DOM Crawler instance', function () {
    $xml = '<items><item match="1">one</item><item match="1">two</item><item>three</item></items>';

    $domCrawler = new Crawler($xml);

    $filtered = (new XPathQuery('//items/item[@match=\'1\']'))->filter($domCrawler);

    expect($filtered)->toBeInstanceOf(Crawler::class);

    expect($filtered->count())->toBe(2);

    expect($filtered->first()->outerHtml())->toBe('<item match="1">one</item>');

    expect($filtered->last()->outerHtml())->toBe('<item match="1">two</item>');
});

it('contains inner tags when the html method is called', function () {
    $xml = '<item>test <sub>sub</sub></item>';

    $domCrawler = new Crawler($xml);

    expect((new XPathQuery('//item'))->html()->apply($domCrawler))->toBe('test <sub>sub</sub>');
});

it('contains also the outer tag when the outerHtml method is called', function () {
    $xml = '<item>test <sub>sub</sub></item>';

    $domCrawler = new Crawler($xml);

    expect((new XPathQuery('//item'))->outerHtml()->apply($domCrawler))->toBe('<item>test <sub>sub</sub></item>');
});

it('does not contain text of children when innerText is called', function () {
    $xml = '<item>test <sub>sub</sub></item>';

    $domCrawler = new Crawler($xml);

    expect((new XPathQuery('//item'))->innerText()->apply($domCrawler))->toBe('test');
});

it('gets the contents of an attribute using the attribute method', function () {
    $xml = '<item attr="content">test</item>';

    $domCrawler = new Crawler($xml);

    expect((new XPathQuery('//item'))->attribute('attr')->apply($domCrawler))->toBe('content');
});
