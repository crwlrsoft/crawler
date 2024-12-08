<?php

namespace tests\Steps\Dom;

use Crwlr\Crawler\Steps\Dom\NodeList;
use Crwlr\Crawler\Steps\Dom\XmlDocument;
use Crwlr\Crawler\Steps\Dom\XmlElement;

$xml = <<<XML
    <?xml version="1.0" encoding="utf-8"?>
    <feed>
        <channelName>foo</channelName>
        <channelIdentifier>foo</channelIdentifier>
        <items>
            <item>
                <id>abc-123</id>
                <updated>2024-11-07T11:00:31Z</updated>
                <title lang="en">Foo bar baz!</title>
                <someUrl>https://www.example.com/item-1?utm_source=foo&amp;utm_medium=feed-xml</someUrl>
                <foo>  <baRbaz>test</baRbaz>  </foo>
            </item>
            <item>
                <id>abc-124</id>
                <updated>2024-12-04T22:43:14Z</updated>
                <title>Lorem Ipsum!</title>
                <someUrl>https://www.example.com/item-2?utm_source=foo&amp;utm_medium=feed-xml</someUrl>
                <foo><baRbaz>hey</baRbaz><quz>ho</quz></foo>
            </item>
        </items>
    </feed>
    XML;

test('child nodes selected via querySelector() are HtmlElement instances', function () use ($xml) {
    $document = new XmlDocument($xml);

    $wrapperElement = $document->querySelector('feed');

    expect($wrapperElement)->toBeInstanceOf(XmlElement::class)
        ->and($wrapperElement?->querySelector('items item'))->toBeInstanceOf(XmlElement::class);
});

test('child nodes selected via querySelectorAll() are HtmlElement instances', function () use ($xml) {
    $document = new XmlDocument($xml);

    $wrapperElement = $document->querySelector('feed');

    expect($wrapperElement)->toBeInstanceOf(XmlElement::class);

    $childNodeList = $wrapperElement?->querySelectorAll('items item');

    expect($childNodeList)->toBeInstanceOf(NodeList::class)
        ->and($childNodeList?->count())->toBe(2)
        ->and($childNodeList?->first())->toBeInstanceOf(XmlElement::class)
        ->and($childNodeList?->last())->toBeInstanceOf(XmlElement::class);
});

it('gets the node name', function () use ($xml) {
    $document = new XmlDocument($xml);

    $node = $document->querySelector('feed');

    expect($node?->nodeName())->toBe('feed')
        ->and($node?->querySelector('items item')?->nodeName())->toBe('item');
});

it('gets the text of a node', function () use ($xml) {
    $document = new XmlDocument($xml);

    $node = $document->querySelector('feed items item:nth-child(2) foo');

    expect($node?->text())->toBe('heyho');
});

it('gets the outer XML of a node', function () use ($xml) {
    $document = new XmlDocument($xml);

    $node = $document->querySelector('feed items item foo baRbaz');

    expect($node?->outerXml())->toBe('<baRbaz>test</baRbaz>');
});

it('gets the inner XML of a node', function () use ($xml) {
    $document = new XmlDocument($xml);

    $node = $document->querySelector('feed items item foo');

    expect($node?->innerXml())->toBe('  <baRbaz>test</baRbaz>  ');
});

it('gets an attribute from a node', function () use ($xml) {
    $document = new XmlDocument($xml);

    $node = $document->querySelector('feed items item:first-child title');

    expect($node?->getAttribute('lang'))->toBe('en');
});
