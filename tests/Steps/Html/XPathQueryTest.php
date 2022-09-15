<?php

namespace tests\Steps\Html;

use Crwlr\Crawler\Steps\Html\XPathQuery;
use Symfony\Component\DomCrawler\Crawler;

use function tests\helper_getSimpleListHtml;

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

test('The apply method returns null if nothing matches', function () {
    $xml = '<item>test</item>';

    $domCrawler = new Crawler($xml);

    expect((new XPathQuery('//aitem'))->apply($domCrawler))->toBeNull();
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

it('turns the value into an absolute url when toAbsoluteUrl() is called', function () {
    $html = '<item>/foo/bar</item>';

    $domCrawler = new Crawler($html);

    $query = (new XPathQuery('//item'))
        ->setBaseUrl('https://www.example.com');

    expect($query->apply($domCrawler))->toBe('/foo/bar');

    $query->toAbsoluteUrl();

    expect($query->apply($domCrawler))->toBe('https://www.example.com/foo/bar');
});

it('gets only the first matching element when the first() method is called', function () {
    $domCrawler = new Crawler(helper_getSimpleListHtml());

    $selector = (new XPathQuery("//*[@id = 'list']/*[contains(@class, 'item')]"))->first();

    expect($selector->apply($domCrawler))->toBe('one');
});

it('gets only the last matching element when the last() method is called', function () {
    $domCrawler = new Crawler(helper_getSimpleListHtml());

    $selector = (new XPathQuery("//*[@id = 'list']/*[contains(@class, 'item')]"))->last();

    expect($selector->apply($domCrawler))->toBe('four');
});

it('gets only the nth matching element when the nth() method is called', function () {
    $domCrawler = new Crawler(helper_getSimpleListHtml());

    $selector = (new XPathQuery("//*[@id = 'list']/*[contains(@class, 'item')]"))->nth(3);

    expect($selector->apply($domCrawler))->toBe('three');
});

it('returns null when no nth matching element exists', function () {
    $domCrawler = new Crawler(helper_getSimpleListHtml());

    $selector = (new XPathQuery("//*[@id = 'list']/*[contains(@class, 'item')]"))->nth(5);

    expect($selector->apply($domCrawler))->toBeNull();
});

it('gets only even matching elements when the even() method is called', function () {
    $domCrawler = new Crawler(helper_getSimpleListHtml());

    $selector = (new XPathQuery("//*[@id = 'list']/*[contains(@class, 'item')]"))->even();

    expect($selector->apply($domCrawler))->toBe(['two', 'four']);
});

it('gets only odd matching elements when the odd() method is called', function () {
    $domCrawler = new Crawler(helper_getSimpleListHtml());

    $selector = (new XPathQuery("//*[@id = 'list']/*[contains(@class, 'item')]"))->odd();

    expect($selector->apply($domCrawler))->toBe(['one', 'three']);
});
