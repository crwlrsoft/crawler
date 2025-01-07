<?php

namespace tests\Steps;

use Crwlr\Crawler\Loader\Http\Messages\RespondedRequest;
use Crwlr\Crawler\Input;
use Crwlr\Crawler\Steps\Dom;
use Crwlr\Crawler\Steps\Html\CssSelector;
use Crwlr\Crawler\Steps\Html\DomQuery;
use Crwlr\Crawler\Steps\Html\XPathQuery;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use stdClass;
use tests\_Stubs\DummyLogger;

use function tests\helper_getStepFilesContent;
use function tests\helper_invokeStepWithInput;
use function tests\helper_traverseIterable;

/**
 * @param mixed[] $mapping
 */
function helper_getDomStepInstance(array $mapping = []): Dom
{
    return new class ($mapping) extends Dom {
        protected function makeDefaultDomQueryInstance(string $query): DomQuery
        {
            return new CssSelector($query);
        }
    };
}

test('string is valid input', function () {
    $html = '<!DOCTYPE html><html><head></head><body><h1>Überschrift</h1></body>';

    $output = helper_invokeStepWithInput(helper_getDomStepInstance()::root(), $html);

    expect($output[0]->get())->toBe([]);
});

test('ResponseInterface is a valid input', function () {
    $output = helper_invokeStepWithInput(helper_getDomStepInstance()::root(), new Response());

    expect($output[0]->get())->toBe([]);
});

test('RespondedRequest is a valid input', function () {
    $output = helper_invokeStepWithInput(
        helper_getDomStepInstance()::root(),
        new RespondedRequest(new Request('GET', '/'), new Response()),
    );

    expect($output[0]->get())->toBe([]);
});

test('For other inputs an error message is logged', function (mixed $input) {
    $logger = new DummyLogger();

    helper_traverseIterable(helper_getDomStepInstance()::root()->addLogger($logger)->invokeStep(new Input($input)));

    expect($logger->messages)->not->toBeEmpty()
        ->and($logger->messages[0]['message'])->toStartWith('A step was called with input that it can not work with: ')
        ->and($logger->messages[0]['message'])->toEndWith('. The invalid input is of type ' . gettype($input) . '.');
})->with([
    [123],
    [123.456],
    [new stdClass()],
]);

it('outputs a single string when argument for extract is a selector string matching only one element', function () {
    $outputs = helper_invokeStepWithInput(
        helper_getDomStepInstance()::root()->extract('.list .item:first-child .match'),
        helper_getStepFilesContent('Html/basic.html'),
    );

    expect($outputs)->toHaveCount(1)
        ->and($outputs[0]->get())->toBe('match 2');
});

it('outputs multiple strings when argument for extract is a selector string matching multiple elements', function () {
    $outputs = helper_invokeStepWithInput(
        helper_getDomStepInstance()::root()->extract('.match'),
        helper_getStepFilesContent('Html/basic.html'),
    );

    expect($outputs)->toHaveCount(3)
        ->and($outputs[0]->get())->toBe('match 1')
        ->and($outputs[2]->get())->toBe('match 3');
});

it('also takes a DomQuery instance as argument for extract', function () {
    $outputs = helper_invokeStepWithInput(
        helper_getDomStepInstance()::root()->extract(Dom::cssSelector('.list .item:first-child .match')),
        helper_getStepFilesContent('Html/basic.html'),
    );

    expect($outputs)->toHaveCount(1)
        ->and($outputs[0]->get())->toBe('match 2');
});

test('Extracting with single selector also works with each', function () {
    $outputs = helper_invokeStepWithInput(
        helper_getDomStepInstance()::each('.list .item')->extract('.match'),
        helper_getStepFilesContent('Html/basic.html'),
    );

    expect($outputs)->toHaveCount(2)
        ->and($outputs[0]->get())->toBe('match 2')
        ->and($outputs[1]->get())->toBe('match 3');
});

test('Extracting with single selector also works with first', function () {
    $outputs = helper_invokeStepWithInput(
        helper_getDomStepInstance()::first('.list .item')->extract('.match'),
        helper_getStepFilesContent('Html/basic.html'),
    );

    expect($outputs)->toHaveCount(1)
        ->and($outputs[0]->get())->toBe('match 2');
});

test('Extracting with single selector also works with last', function () {
    $outputs = helper_invokeStepWithInput(
        helper_getDomStepInstance()::last('.list .item')->extract('.match'),
        helper_getStepFilesContent('Html/basic.html'),
    );

    expect($outputs)->toHaveCount(1)
        ->and($outputs[0]->get())->toBe('match 3');
});

test('Extracting with single selector that doesn\'t match anything doesn\'t yield any output', function () {
    $outputs = helper_invokeStepWithInput(
        helper_getDomStepInstance()::last('.list .item')->extract('.mätch'),
        helper_getStepFilesContent('Html/basic.html'),
    );

    expect($outputs)->toHaveCount(0);
});

it('extracts one result from the root node when the root method is used', function () {
    $output = helper_invokeStepWithInput(
        helper_getDomStepInstance()::root()->extract(['matches' => '.match']),
        helper_getStepFilesContent('Html/basic.html'),
    );

    expect($output)->toHaveCount(1)
        ->and($output[0]->get())->toBe(['matches' => ['match 1', 'match 2', 'match 3']]);
});

it('extracts each matching result when the each method is used', function () {
    $output = helper_invokeStepWithInput(
        helper_getDomStepInstance()::each('.list .item')->extract(['match' => '.match']),
        helper_getStepFilesContent('Html/basic.html'),
    );

    expect($output)->toHaveCount(2)
        ->and($output[0]->get())->toBe(['match' => 'match 2'])
        ->and($output[1]->get())->toBe(['match' => 'match 3']);
});

it('extracts the first matching result when the first method is used', function () {
    $output = helper_invokeStepWithInput(
        helper_getDomStepInstance()::first('.list .item')->extract(['match' => '.match']),
        helper_getStepFilesContent('Html/basic.html'),
    );

    expect($output)->toHaveCount(1)
        ->and($output[0]->get())->toBe(['match' => 'match 2']);
});

it('extracts the last matching result when the last method is used', function () {
    $output = helper_invokeStepWithInput(
        helper_getDomStepInstance()::last('.list .item')->extract(['match' => '.match']),
        helper_getStepFilesContent('Html/basic.html'),
    );

    expect($output)->toHaveCount(1)
        ->and($output[0]->get())->toBe(['match' => 'match 3']);
});

it('doesn\'t yield any output when the each selector doesn\'t match anything', function () {
    $output = helper_invokeStepWithInput(
        helper_getDomStepInstance()::each('.list .ytem')->extract(['match' => '.match']),
        helper_getStepFilesContent('Html/basic.html'),
    );

    expect($output)->toHaveCount(0);
});

it('doesn\'t yield any output when the first selector doesn\'t match anything', function () {
    $output = helper_invokeStepWithInput(
        helper_getDomStepInstance()::first('.list .ytem')->extract(['match' => '.match']),
        helper_getStepFilesContent('Html/basic.html'),
    );

    expect($output)->toHaveCount(0);
});

it('doesn\'t yield any output when the last selector doesn\'t match anything', function () {
    $output = helper_invokeStepWithInput(
        helper_getDomStepInstance()::last('.list .otem')->extract(['match' => '.match']),
        helper_getStepFilesContent('Html/basic.html'),
    );

    expect($output)->toHaveCount(0);
});

it('returns an array with null values when selectors in an extract array mapping don\'t match anything', function () {
    $output = helper_invokeStepWithInput(
        helper_getDomStepInstance()::last('.list .item')->extract(['match' => '.match', 'noMatch' => '.doesntMatch']),
        helper_getStepFilesContent('Html/basic.html'),
    );

    expect($output)->toHaveCount(1)
        ->and($output[0]->get())->toBe(['match' => 'match 3', 'noMatch' => null]);
});

test('The static cssSelector method returns an instance of CssSelector using the provided selector', function () {
    $cssSelector = Dom::cssSelector('.item');

    expect($cssSelector)->toBeInstanceOf(CssSelector::class);

    $itemContent = $cssSelector->apply(new Dom\HtmlDocument('<span class="item">yes</span>'));

    expect($itemContent)->toBe('yes');
});

test('The static xPath method returns an instance of XPathQuery using the provided query', function () {
    $xPathQuery = Dom::xPath('//item');

    expect($xPathQuery)->toBeInstanceOf(XPathQuery::class);

    $itemContent = $xPathQuery->apply(new Dom\XmlDocument('<item>yes</item>'));

    expect($itemContent)->toBe('yes');
});

it('uses the keys of the provided mapping as keys in the returned output', function () {
    $output = helper_invokeStepWithInput(
        helper_getDomStepInstance()::root()->extract(['foo' => '.foo', 'notBar' => '.bar', '.baz']),
        '<p class="foo">foo content</p><p class="bar">bar content</p><p class="baz">baz content</p>',
    );

    expect($output)->toHaveCount(1)
        ->and($output[0]->get())->toBe(['foo' => 'foo content', 'notBar' => 'bar content', 0 => 'baz content']);
});

it('trims the extracted data', function () {
    $output = helper_invokeStepWithInput(
        helper_getDomStepInstance()::root()->extract(['foo' => '.foo']),
        "<p class=\"foo\">  \n   foo content   \n   \n</p>",
    );

    expect($output)->toHaveCount(1)
        ->and($output[0]->get())->toBe(['foo' => 'foo content']);
});

it('automatically passes on the base url to dom query instances when the input is a RespondedRequest', function () {
    $output = helper_invokeStepWithInput(
        helper_getDomStepInstance()::root()->extract([
            'one' => Dom::cssSelector('#one')->attribute('href')->toAbsoluteUrl(),
            'two' => Dom::cssSelector('#two')->link(),
        ]),
        new RespondedRequest(
            new Request('GET', 'https://www.example.com/home'),
            new Response(body: '<p><a id="one" href="/foo/bar">foo bar</a> <a id="two" href="yo/lo">yolo</a></p>'),
        ),
    );

    expect($output)->toHaveCount(1)
        ->and($output[0]->get())->toBe([
            'one' => 'https://www.example.com/foo/bar',
            'two' => 'https://www.example.com/yo/lo',
        ]);
});

it('removes the fragment part from URLs when the withoutFragment method is called on a DomQuery instance', function () {
    $body = <<<HTML
        <p>
            <a id="one" href="/foo#foo">one</a> <br>
            <a id="two" href="/bar#bar">two</a> <br>
            <a id="three" href="/baz#baz">three</a> <br>
            <a id="four" href="/quz#quz">four</a> <br>
        </p>
        HTML;

    $output = helper_invokeStepWithInput(
        helper_getDomStepInstance()::root()->extract([
            'one' => Dom::cssSelector('#one')->link(),
            'two' => Dom::xPath('//a[@id=\'two\']')->link(),
            'three' => Dom::cssSelector('#three')->link()->withoutFragment(),
            'four' => Dom::xPath('//a[@id=\'four\']')->link()->withoutFragment(),
        ]),
        new RespondedRequest(
            new Request('GET', 'https://www.example.com/home'),
            new Response(body: $body),
        ),
    );

    expect($output)->toHaveCount(1)
        ->and($output[0]->get())->toBe([
            'one' => 'https://www.example.com/foo#foo',
            'two' => 'https://www.example.com/bar#bar',
            'three' => 'https://www.example.com/baz',
            'four' => 'https://www.example.com/quz',
        ]);
});
