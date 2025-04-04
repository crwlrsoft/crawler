<?php

namespace tests\Steps\Dom;

use Crwlr\Crawler\Steps\Dom\NodeList;
use Crwlr\Crawler\Steps\Dom\XmlDocument;
use Crwlr\Crawler\Steps\Dom\XmlElement;

test('the querySelector() method returns an XmlElement object', function () {
    $xml = <<<XML
        <?xml version="1.0" encoding="utf-8"?>
        <feed>
            <items><item><id>1</id></item></items>
        </feed>
        XML;

    $document = new XmlDocument($xml);

    expect($document->querySelector('feed items item'))->toBeInstanceOf(XmlElement::class);
});

test('the querySelectorAll() method returns a NodeList of XmlElement objects', function () {
    $xml = <<<XML
        <?xml version="1.0" encoding="utf-8"?>
        <feed>
            <items><item><id>1</id></item><item><id>2</id></item><item><id>3</id></item></items>
        </feed>
        XML;

    $document = new XmlDocument($xml);

    $nodeList = $document->querySelectorAll('feed items item');

    expect($nodeList)->toBeInstanceOf(NodeList::class);

    $anyNodesChecked = false;

    foreach ($nodeList as $node) {
        expect($node)->toBeInstanceOf(XmlElement::class);

        $anyNodesChecked = true;
    }

    expect($anyNodesChecked)->toBeTrue();
});

test('the queryXPath() method returns a NodeList of XmlElement objects', function () {
    $xml = <<<XML
        <?xml version="1.0" encoding="utf-8"?>
        <feed>
            <items><item><id>1</id></item><item><id>2</id></item><item><id>3</id></item></items>
        </feed>
        XML;

    $document = new XmlDocument($xml);

    $nodeList = $document->queryXPath('//feed/items/item');

    expect($nodeList)->toBeInstanceOf(NodeList::class);

    $anyNodesChecked = false;

    foreach ($nodeList as $node) {
        expect($node)->toBeInstanceOf(XmlElement::class);

        $anyNodesChecked = true;
    }

    expect($anyNodesChecked)->toBeTrue();
});

//it('is able to parse documents containing characters that aren\'t valid within XML documents', function (string $char) {
//    $xml = <<<XML
//        <?xml version="1.0" encoding="UTF-8"?>
//        <rss>
//        <channel>
//        <items>
//        <item>
//        <title><![CDATA[foo - {$char} - bar]]></title>
//        </item>
//        </items>
//        </channel>
//        </rss>
//        XML;
//
//    $document = new XmlDocument($xml);
//
//    $titles = $document->querySelectorAll('channel item title');
//
//    expect($titles)->toBeInstanceOf(NodeList::class)
//        ->and($titles->count())->toBe(1)
//        ->and($titles->first()?->text())->toStartWith('foo - ')
//        ->and($titles->first()?->text())->toEndWith(' - bar');
//})->with([
//    [mb_chr(0)],
//    [mb_chr(6)],
//    [mb_chr(12)],
//    [mb_chr(20)],
//    [mb_chr(31)],
//    [mb_chr(128)],
//    [mb_chr(157)],
//    [mb_chr(195)],
//    [mb_chr(253)],
//])->only();
