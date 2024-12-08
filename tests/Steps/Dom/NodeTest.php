<?php

namespace Tests\Steps\Dom;

use Crwlr\Crawler\Steps\Dom\HtmlElement;
use Crwlr\Crawler\Steps\Dom\Node;
use Crwlr\Crawler\Steps\Dom\NodeList;
use Crwlr\Crawler\Steps\Dom\XmlElement;
use Dom\HTMLDocument;
use Dom\XMLDocument;
use DOMNode;
use Exception;
use Symfony\Component\DomCrawler\Crawler;
use tests\Steps\Dom\_Stubs\HtmlNodeStub;
use tests\Steps\Dom\_Stubs\XmlNodeStub;

function helper_getSymfonyCrawlerInstanceFromSource(string $source, string $selectNode = 'body'): Crawler
{
    return (new Crawler($source))->filter($selectNode)->first();
}

/**
 * @throws Exception
 */
function helper_getLegacyDomNodeInstanceFromSource(string $source, string $selectNode = 'body'): DOMNode
{
    $node = (new Crawler($source))->filter($selectNode)->first()->getNode(0);

    if (!$node) {
        throw new Exception('Can\'t get legacy node');
    }

    return $node;
}

function helper_getPhp84HtmlDomNodeInstanceFromSource(string $source, string $selectNode = 'body'): \Dom\Node
{
    return HTMLDocument::createFromString($source, LIBXML_NOERROR)->querySelector($selectNode);
}

function helper_getPhp84XmlDomNodeInstanceFromSource(string $source, string $selectNode = 'body'): \Dom\Node
{
    return XMLDocument::createFromString($source, LIBXML_NOERROR)->querySelector($selectNode);
}

/**
 * @param \Dom\Node|DOMNode|Crawler $originalNode
 */
function helper_getAbstractNodeInstance(object $originalNode, bool $html = true): HtmlNodeStub|XmlNodeStub
{
    if ($html) {
        return new HtmlNodeStub($originalNode);
    }

    return new XmlNodeStub($originalNode);
}

it('can be created from a \DOM\Node instance', function () {
    $xml = <<<XML
        <?xml version="1.0" encoding="utf-8"?>
        <items>
            <item><id>1</id><title>Foo</title></item>
        </items>
        XML;

    $domNode = helper_getPhp84XmlDomNodeInstanceFromSource($xml, 'items item');

    expect($domNode)->toBeInstanceOf(\Dom\Node::class);

    $node = new class ($domNode) extends Node {
        protected function makeChildNodeInstance(object $node): Node
        {
            return new XmlElement($node);
        }
    };

    expect($node)->toBeInstanceOf(Node::class)
        ->and($node->text())->toBe('1Foo');
})->group('php84');

it('can be instantiated from a symfony Crawler instance', function () {
    $xml = <<<XML
        <?xml version="1.0" encoding="utf-8"?>
        <items>
            <item><id>1</id><title>Foo</title></item>
        </items>
        XML;

    $crawler = helper_getSymfonyCrawlerInstanceFromSource($xml, 'items item');

    expect($crawler)->toBeInstanceOf(Crawler::class);

    $node = new class ($crawler) extends Node {
        protected function makeChildNodeInstance(object $node): Node
        {
            return new XmlElement($node);
        }
    };

    expect($node)->toBeInstanceOf(Node::class)
        ->and($node->text())->toBe('1Foo');
});

it('can be instantiated from a DOMNode instance', function () {
    $xml = <<<XML
        <?xml version="1.0" encoding="utf-8"?>
        <items>
            <item><id>1</id><title>Foo</title></item>
        </items>
        XML;

    $domNode = helper_getLegacyDomNodeInstanceFromSource($xml, 'items item');

    expect($domNode)->toBeInstanceOf(DOMNode::class);

    $node = new class ($domNode) extends Node {
        protected function makeChildNodeInstance(object $node): Node
        {
            return new XmlElement($node);
        }
    };

    expect($node)->toBeInstanceOf(Node::class)
        ->and($node->text())->toBe('1Foo');
});

$html = <<<HTML
        <html>
        <head>
            <title>Foo</title>
        </head>
        <body>
            <div class="foo">
                <h1>Title</h1>
            </div>
        </body>
        </html>
        HTML;

it('selects an element within a node via querySelector()', function (object $originalNode) {
    /** @var Crawler|DOMNode $originalNode */
    $node = helper_getAbstractNodeInstance($originalNode);

    $selectedNode = $node->querySelector('.foo h1');

    expect($selectedNode)->toBeInstanceOf(Node::class)
        ->and($selectedNode?->text())->toBe('Title');
})->with([
    [helper_getSymfonyCrawlerInstanceFromSource($html)],
    [helper_getLegacyDomNodeInstanceFromSource($html)],
]);

it('selects an element within a node via querySelector() in PHP >= 8.4', function () use ($html) {
    $originalNode = helper_getPhp84HtmlDomNodeInstanceFromSource($html);

    $node = helper_getAbstractNodeInstance($originalNode);

    $selectedNode = $node->querySelector('.foo h1');

    expect($selectedNode)->toBeInstanceOf(Node::class)
        ->and($selectedNode?->text())->toBe('Title');
})->group('php84');

$html = <<<HTML
    <html>
    <head><title>Bar</title></head>
    <body>
        <div class="foo">
            <h2>Foo</h2>
        </div>
        <div class="foo">
            <h2>Bar</h2>
        </div>
    </body>
    </html>
    HTML;

test(
    'querySelector() selects the first element within a node, when multiple nodes match a selector',
    function (object $originalNode) {
        /** @var Crawler|DOMNode $originalNode */
        $node = helper_getAbstractNodeInstance($originalNode);

        $selectedNode = $node->querySelector('.foo h2');

        expect($selectedNode)->toBeInstanceOf(Node::class)
            ->and($selectedNode?->text())->toBe('Foo');
    },
)->with([
    [helper_getSymfonyCrawlerInstanceFromSource($html)],
    [helper_getLegacyDomNodeInstanceFromSource($html)],
]);

it(
    'selects the first element within a node using querySelector(), when multiple nodes match a selector in PHP >= 8.4',
    function () use ($html) {
        $originalNode = helper_getPhp84HtmlDomNodeInstanceFromSource($html);

        $node = helper_getAbstractNodeInstance($originalNode);

        $selectedNode = $node->querySelector('.foo h2');

        expect($selectedNode)->toBeInstanceOf(Node::class)
            ->and($selectedNode?->text())->toBe('Foo');
    },
)->group('php84');

$html = <<<HTML
    <html>
    <head><title>Foo</title></head>
    <body>
        yo
    </body>
    </html>
    HTML;

it('returns null when the selector passed to querySelector() matches nothing', function (object $originalNode) {
    /** @var Crawler|DOMNode $originalNode */
    $node = helper_getAbstractNodeInstance($originalNode);

    $selectedNode = $node->querySelector('.foo h2');

    expect($selectedNode)->toBeNull();
})->with([
    [helper_getSymfonyCrawlerInstanceFromSource($html)],
    [helper_getLegacyDomNodeInstanceFromSource($html)],
]);

it('returns null when the selector passed to querySelector() matches nothing in PHP >= 8.4', function () use ($html) {
    $originalNode = helper_getPhp84HtmlDomNodeInstanceFromSource($html);

    $node = helper_getAbstractNodeInstance($originalNode);

    $selectedNode = $node->querySelector('.foo h2');

    expect($selectedNode)->toBeNull();
})->group('php84');

$xml = <<<XML
    <?xml version="1.0" encoding="utf-8"?>
    <feed>
      <items>
        <item><id>1</id><title>Foo</title></item>
        <item><id>2</id><title>Bar</title></item>
        <item><id>3</id><title>Baz</title></item>
      </items>
    </feed>
    XML;

it('selects all elements within a node, matching a selector using querySelectorAll()', function (object $originalNode) {
    /** @var Crawler|DOMNode $originalNode */
    $node = helper_getAbstractNodeInstance($originalNode);

    $selected = $node->querySelectorAll('items item title');

    expect($selected)->toBeInstanceOf(NodeList::class)
        ->and($selected->count())->toBe(3)
        ->and($selected->first()?->text())->toBe('Foo')
        ->and($selected->nth(2)?->text())->toBe('Bar')
        ->and($selected->last()?->text())->toBe('Baz');
})->with([
    [helper_getSymfonyCrawlerInstanceFromSource($xml, 'feed')],
    [helper_getLegacyDomNodeInstanceFromSource($xml, 'feed')],
]);

it(
    'selects all elements within a node, matching a selector using querySelectorAll() in PHP >= 8.4',
    function () use ($xml) {
        $originalNode = helper_getPhp84XmlDomNodeInstanceFromSource($xml, 'feed');

        $node = helper_getAbstractNodeInstance($originalNode);

        $selected = $node->querySelectorAll('items item title');

        expect($selected)->toBeInstanceOf(NodeList::class)
            ->and($selected->count())->toBe(3)
            ->and($selected->first()?->text())->toBe('Foo')
            ->and($selected->nth(2)?->text())->toBe('Bar')
            ->and($selected->last()?->text())->toBe('Baz');
    },
)->group('php84');

$xml = <<<XML
    <?xml version="1.0" encoding="utf-8"?>
    <feed>
        <items><item><id>1</id></item><item><id>2</id></item><item><id>3</id></item></items>
    </feed>
    XML;

it(
    'gets an empty NodeList when nothing matches the selector passed to querySelectorAll()',
    function (object $originalNode) {
        /** @var Crawler|DOMNode $originalNode */
        $node = helper_getAbstractNodeInstance($originalNode);

        $selected = $node->querySelectorAll('items item author');

        expect($selected)->toBeInstanceOf(NodeList::class)
            ->and($selected->count())->toBe(0);
    },
)->with([
    [helper_getSymfonyCrawlerInstanceFromSource($xml, 'feed')],
    [helper_getLegacyDomNodeInstanceFromSource($xml, 'feed')],
]);

it(
    'gets an empty NodeList when nothing matches the selector passed to querySelectorAll() in PHP >= 8.4',
    function () use ($xml) {
        $originalNode = helper_getPhp84XmlDomNodeInstanceFromSource($xml, 'feed');

        $node = helper_getAbstractNodeInstance($originalNode);

        $selected = $node->querySelectorAll('items item author');

        expect($selected)->toBeInstanceOf(NodeList::class)
            ->and($selected->count())->toBe(0);
    },
)->group('php84');

$html = <<<HTML
    <html>
    <head><title>Lorem Ipsum</title></head>
    <body>
        <ul><li>hip</li><li>hop</li><li>hooray</li></ul>
    </body>
    </html>
    HTML;

it(
    'selects all elements within a node, matching an XPath query using queryXPath()',
    function (object $originalNode) {
        /** @var Crawler|DOMNode $originalNode */
        $node = helper_getAbstractNodeInstance($originalNode);

        $selected = $node->queryXPath('//ul/li');

        expect($selected)->toBeInstanceOf(NodeList::class)
            ->and($selected->count())->toBe(3)
            ->and($selected->first()?->text())->toBe('hip')
            ->and($selected->nth(2)?->text())->toBe('hop')
            ->and($selected->last()?->text())->toBe('hooray');
    },
)->with([
    [helper_getSymfonyCrawlerInstanceFromSource($html)],
    [helper_getLegacyDomNodeInstanceFromSource($html)],
]);

it(
    'selects all elements within a node, matching an XPath query using queryXPath() in PHP >= 8.4',
    function () use ($html) {
        $originalNode = helper_getPhp84HtmlDomNodeInstanceFromSource($html);

        $node = helper_getAbstractNodeInstance($originalNode);

        $selected = $node->queryXPath('//ul/li');

        expect($selected)->toBeInstanceOf(NodeList::class)
            ->and($selected->count())->toBe(3)
            ->and($selected->first()?->text())->toBe('hip')
            ->and($selected->nth(2)?->text())->toBe('hop')
            ->and($selected->last()?->text())->toBe('hooray');
    },
)->group('php84');

it('gets an empty NodeList when nothing matches the selector passed to queryXPath()', function (object $originalNode) {
    /** @var Crawler|DOMNode $originalNode */
    $node = helper_getAbstractNodeInstance($originalNode);

    $selected = $node->queryXPath('//ul/li/strong');

    expect($selected)->toBeInstanceOf(NodeList::class)
        ->and($selected->count())->toBe(0);
})->with([
    [helper_getSymfonyCrawlerInstanceFromSource($html)],
    [helper_getLegacyDomNodeInstanceFromSource($html)],
]);

it(
    'gets an empty NodeList when nothing matches the selector passed to queryXPath() in PHP => 8.4',
    function () use ($html) {
        $originalNode = helper_getPhp84HtmlDomNodeInstanceFromSource($html);

        $node = helper_getAbstractNodeInstance($originalNode);

        $selected = $node->queryXPath('//ul/li/strong');

        expect($selected)->toBeInstanceOf(NodeList::class)
            ->and($selected->count())->toBe(0);
    },
)->group('php84');

$html = <<<HTML
    <html>
    <head><title>Foo</title></head>
    <body>
        <div class="element" data-test="hi"></div>
    </body>
    </html>
    HTML;

it('gets the value of an attribute', function (object $originalNode) {
    /** @var Crawler|DOMNode $originalNode */
    $node = helper_getAbstractNodeInstance($originalNode);

    expect($node->getAttribute('data-test'))->toBe('hi');
})->with([
    [helper_getSymfonyCrawlerInstanceFromSource($html, '.element')],
    [helper_getLegacyDomNodeInstanceFromSource($html, '.element')],
]);

it('gets the value of an attribute in PHP >= 8.4', function () use ($html) {
    $originalNode = helper_getPhp84HtmlDomNodeInstanceFromSource($html, '.element');

    $node = helper_getAbstractNodeInstance($originalNode);

    expect($node->getAttribute('data-test'))->toBe('hi');
})->group('php84');

$html = <<<HTML
    <html>
    <head><title>Foo</title></head>
    <body><div class="element"></div></body>
    </html>
    HTML;

it('returns null when an attribute does not exist', function (object $originalNode) {
    /** @var Crawler|DOMNode $originalNode */
    $node = helper_getAbstractNodeInstance($originalNode);

    expect($node->getAttribute('data-test'))->toBeNull();
})->with([
    [helper_getSymfonyCrawlerInstanceFromSource($html, '.element')],
    [helper_getLegacyDomNodeInstanceFromSource($html, '.element')],
]);

it('returns null when an attribute does not exist in PHP >= 8.4', function () use ($html) {
    $originalNode = helper_getPhp84HtmlDomNodeInstanceFromSource($html, '.element');

    $node = helper_getAbstractNodeInstance($originalNode);

    expect($node->getAttribute('data-test'))->toBeNull();
})->group('php84');

it('gets the name of a node', function (object $originalNode) {
    /** @var Crawler|DOMNode $originalNode */
    $node = helper_getAbstractNodeInstance($originalNode);

    expect($node->nodeName())->toBe('div');
})->with([
    [helper_getSymfonyCrawlerInstanceFromSource($html, '.element')],
    [helper_getLegacyDomNodeInstanceFromSource($html, '.element')],
]);

it('gets the name of a node in PHP >= 8.4', function () use ($html) {
    $originalNode = helper_getPhp84HtmlDomNodeInstanceFromSource($html, '.element');

    $node = helper_getAbstractNodeInstance($originalNode);

    expect($node->nodeName())->toBe('div');
})->group('php84');

$html = <<<HTML
    <html>
    <head><title>Bar</title></head>
    <body>
        <article> <h1>Title</h1> <p>Lorem ipsum.</p> </article>
    </body>
    </html>
    HTML;

it('gets the text content of an HTML node', function (object $originalNode) {
    /** @var Crawler|DOMNode $originalNode */
    $node = helper_getAbstractNodeInstance($originalNode);

    expect($node->text())->toBe('Title Lorem ipsum.');
})->with([
    [helper_getSymfonyCrawlerInstanceFromSource($html, 'article')],
    [helper_getLegacyDomNodeInstanceFromSource($html, 'article')],
]);

it('gets the text content of an HTML node in PHP >= 8.4', function () use ($html) {
    $originalNode = helper_getPhp84HtmlDomNodeInstanceFromSource($html, 'article');

    $node = helper_getAbstractNodeInstance($originalNode);

    expect($node->text())->toBe('Title Lorem ipsum.');
})->group('php84');

it('gets the inner source of an HTML node', function (object $originalNode) {
    /** @var Crawler|DOMNode $originalNode */
    $node = helper_getAbstractNodeInstance($originalNode);

    expect($node->inner())->toBe(' <h1>Title</h1> <p>Lorem ipsum.</p> ');
})->with([
    [helper_getSymfonyCrawlerInstanceFromSource($html, 'article')],
    [helper_getLegacyDomNodeInstanceFromSource($html, 'article')],
]);

it('gets the inner source of an HTML node in PHP >= 8.4', function () use ($html) {
    $originalNode = helper_getPhp84HtmlDomNodeInstanceFromSource($html, 'article');

    $node = helper_getAbstractNodeInstance($originalNode);

    expect($node->inner())->toBe(' <h1>Title</h1> <p>Lorem ipsum.</p> ');
})->group('php84');

it('gets the outer source of an HTML node', function (object $originalNode) {
    /** @var Crawler|DOMNode $originalNode */
    $node = helper_getAbstractNodeInstance($originalNode);

    expect($node->outer())->toBe('<article> <h1>Title</h1> <p>Lorem ipsum.</p> </article>');
})->with([
    [helper_getSymfonyCrawlerInstanceFromSource($html, 'article')],
    [helper_getLegacyDomNodeInstanceFromSource($html, 'article')],
]);

it('gets the outer source of an HTML node in PHP >= 8.4', function () use ($html) {
    $originalNode = helper_getPhp84HtmlDomNodeInstanceFromSource($html, 'article');

    $node = helper_getAbstractNodeInstance($originalNode);

    expect($node->outer())->toBe('<article> <h1>Title</h1> <p>Lorem ipsum.</p> </article>');
})->group('php84');

$xml = <<<XML
    <?xml version="1.0" encoding="utf-8"?>
    <items> <item> <id>1</id> <title>Lorem Ipsum</title> </item> </items>
    XML;

it('gets the text content of an XML node', function (object $originalNode) {
    /** @var Crawler|DOMNode $originalNode */
    $node = helper_getAbstractNodeInstance($originalNode);

    expect($node->text())->toBe('1 Lorem Ipsum');
})->with([
    [helper_getSymfonyCrawlerInstanceFromSource($xml, 'items item')],
    [helper_getLegacyDomNodeInstanceFromSource($xml, 'items item')],
]);

it('gets the text content of an XML node in PHP >= 8.4', function () use ($xml) {
    $originalNode = helper_getPhp84XmlDomNodeInstanceFromSource($xml, 'items item');

    $node = helper_getAbstractNodeInstance($originalNode);

    expect($node->text())->toBe('1 Lorem Ipsum');
})->group('php84');

it('gets the inner source of an XML node', function (object $originalNode) {
    /** @var Crawler|DOMNode $originalNode */
    $node = helper_getAbstractNodeInstance($originalNode);

    expect($node->inner())->toBe(' <id>1</id> <title>Lorem Ipsum</title> ');
})->with([
    [helper_getSymfonyCrawlerInstanceFromSource($xml, 'items item')],
    [helper_getLegacyDomNodeInstanceFromSource($xml, 'items item')],
]);

it('gets the inner source of an XML node in PHP >= 8.4', function () use ($xml) {
    $originalNode = helper_getPhp84XmlDomNodeInstanceFromSource($xml, 'items item');

    $node = helper_getAbstractNodeInstance($originalNode);

    expect($node->inner())->toBe(' <id>1</id> <title>Lorem Ipsum</title> ');
})->group('php84');

it('gets the outer source of an XML node', function (object $originalNode) {
    /** @var Crawler|DOMNode $originalNode */
    $node = helper_getAbstractNodeInstance($originalNode);

    expect($node->outer())->toBe('<item> <id>1</id> <title>Lorem Ipsum</title> </item>');
})->with([
    [helper_getSymfonyCrawlerInstanceFromSource($xml, 'items item')],
    [helper_getLegacyDomNodeInstanceFromSource($xml, 'items item')],
]);

it('gets the outer source of an XML node in PHP >= 8.4', function () use ($xml) {
    $originalNode = helper_getPhp84XmlDomNodeInstanceFromSource($xml, 'items item');

    $node = helper_getAbstractNodeInstance($originalNode);

    expect($node->outer())->toBe('<item> <id>1</id> <title>Lorem Ipsum</title> </item>');
})->group('php84');

$html = <<<HTML
    <html>
    <head><title>Bar</title></head>
    <body>
        <ul><li class="foo">one</li></ul>

        <ul><li>foo</li></ul>
    </body>
    </html>
    HTML;

it('selects elements using a CSS selector containing the :has() pseudo class', function () use ($html) {
    $originalNode = helper_getPhp84HtmlDomNodeInstanceFromSource($html);

    $node = helper_getAbstractNodeInstance($originalNode);

    $selected = $node->querySelector('ul:has(.foo)');

    expect($selected)->toBeInstanceOf(HtmlElement::class)
        ->and($selected?->text())->toBe('one');
})->group('php84');

it('selects elements using a CSS selector containing the :not() pseudo class', function () use ($html) {
    $originalNode = helper_getPhp84HtmlDomNodeInstanceFromSource($html);

    $node = helper_getAbstractNodeInstance($originalNode);

    $selected = $node->querySelector('ul:not(:has(.foo))');

    expect($selected)->toBeInstanceOf(HtmlElement::class)
        ->and($selected?->text())->toBe('foo');
})->group('php84');
