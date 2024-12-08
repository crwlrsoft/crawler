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
