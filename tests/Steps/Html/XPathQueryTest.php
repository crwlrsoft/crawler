<?php

namespace tests\Steps\Html;

use Crwlr\Crawler\Steps\Dom\HtmlDocument;
use Crwlr\Crawler\Steps\Dom\XmlDocument;
use Crwlr\Crawler\Steps\Html\Exceptions\InvalidDomQueryException;
use Crwlr\Crawler\Steps\Html\XPathQuery;

use function tests\helper_getSimpleListHtml;

it('throws an exception when created with an invalid XPath query', function () {
    new XPathQuery('//a/@@bob/uncle');
})->throws(InvalidDomQueryException::class);

test('The apply method returns a string for a single match', function () {
    $xml = '<item>test</item>';

    expect((new XPathQuery('//item'))->apply(new XmlDocument($xml)))->toBe('test');
});

test('The apply method returns an array of strings for multiple matches', function () {
    $html = '<item>test</item><item>test 2 <test>sub</test></item><item>test 3</item>';

    expect((new XPathQuery('//item'))->apply(new HtmlDocument($html)))->toBe(['test', 'test 2 sub', 'test 3']);
});

test('The apply method returns null if nothing matches', function () {
    $xml = '<item>test</item>';

    expect((new XPathQuery('//aitem'))->apply(new XmlDocument($xml)))->toBeNull();
});

it('trims whitespace', function () {
    $xml = <<<XML
        <item>
            test
        </item>
        XML;

    expect((new XPathQuery('//item'))->apply(new XmlDocument($xml)))->toBe('test');
});

it('contains inner tags when the html method is called', function () {
    $xml = '<item>test <sub>sub</sub></item>';

    expect((new XPathQuery('//item'))->html()->apply(new XmlDocument($xml)))->toBe('test <sub>sub</sub>');
});

it('contains also the outer tag when the outerHtml method is called', function () {
    $xml = '<item>test <sub>sub</sub></item>';

    expect((new XPathQuery('//item'))->outerHtml()->apply(new XmlDocument($xml)))->toBe('<item>test <sub>sub</sub></item>');
});

it('gets the contents of an attribute using the attribute method', function () {
    $xml = '<item attr="content">test</item>';

    expect((new XPathQuery('//item'))->attribute('attr')->apply(new XmlDocument($xml)))->toBe('content');
});

it('turns the value into an absolute url when toAbsoluteUrl() is called', function () {
    $xml = '<item>/foo/bar</item>';

    $document = new XmlDocument($xml);

    $query = (new XPathQuery('//item'))
        ->setBaseUrl('https://www.example.com');

    expect($query->apply($document))->toBe('/foo/bar');

    $query->toAbsoluteUrl();

    expect($query->apply($document))->toBe('https://www.example.com/foo/bar');
});

it('gets an absolute link from the href attribute of a link element, when the link() method is called', function () {
    $html = '<div id="foo"><a class="bar" href="/foo/bar">Foo</a></div>';

    $document = new HtmlDocument($html);

    $selector = (new XPathQuery('//*[@id=\'foo\']/a[@class=\'bar\']'))
        ->setBaseUrl('https://www.example.com/');

    expect($selector->apply($document))->toBe('Foo');

    $selector->link();

    expect($selector->apply($document))->toBe('https://www.example.com/foo/bar');
});

it('gets only the first matching element when the first() method is called', function () {
    $selector = (new XPathQuery("//*[@id = 'list']/*[contains(@class, 'item')]"))->first();

    expect($selector->apply(new HtmlDocument(helper_getSimpleListHtml())))->toBe('one');
});

it('gets only the last matching element when the last() method is called', function () {
    $selector = (new XPathQuery("//*[@id = 'list']/*[contains(@class, 'item')]"))->last();

    expect($selector->apply(new HtmlDocument(helper_getSimpleListHtml())))->toBe('four');
});

it('gets only the nth matching element when the nth() method is called', function () {
    $selector = (new XPathQuery("//*[@id = 'list']/*[contains(@class, 'item')]"))->nth(3);

    expect($selector->apply(new HtmlDocument(helper_getSimpleListHtml())))->toBe('three');
});

it('returns null when no nth matching element exists', function () {
    $selector = (new XPathQuery("//*[@id = 'list']/*[contains(@class, 'item')]"))->nth(5);

    expect($selector->apply(new HtmlDocument(helper_getSimpleListHtml())))->toBeNull();
});

it('gets only even matching elements when the even() method is called', function () {
    $selector = (new XPathQuery("//*[@id = 'list']/*[contains(@class, 'item')]"))->even();

    expect($selector->apply(new HtmlDocument(helper_getSimpleListHtml())))->toBe(['two', 'four']);
});

it('gets only odd matching elements when the odd() method is called', function () {
    $selector = (new XPathQuery("//*[@id = 'list']/*[contains(@class, 'item')]"))->odd();

    expect($selector->apply(new HtmlDocument(helper_getSimpleListHtml())))->toBe(['one', 'three']);
});
