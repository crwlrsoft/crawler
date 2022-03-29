<?php

namespace tests\Steps;

use Crwlr\Crawler\Aggregates\RequestResponseAggregate;
use Crwlr\Crawler\Input;
use Crwlr\Crawler\Steps\Dom;
use Crwlr\Crawler\Steps\Html\CssSelector;
use Crwlr\Crawler\Steps\Html\DomQueryInterface;
use Crwlr\Crawler\Steps\Html\XPathQuery;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use InvalidArgumentException;
use stdClass;
use Symfony\Component\DomCrawler\Crawler;
use function tests\helper_generatorToArray;
use function tests\helper_getStepFilesContent;
use function tests\helper_traverseIterable;

/**
 * @param mixed[] $mapping
 */
function helper_getDomStepInstance(array $mapping = []): Dom
{
    return new class ($mapping) extends Dom {
        protected function makeDefaultDomQueryInstance(string $query): DomQueryInterface
        {
            return new CssSelector($query);
        }
    };
}

test('string is valid input', function () {
    $html = '<!DOCTYPE html><html><head></head><body><h1>Überschrift</h1></body>';

    $domStep = helper_getDomStepInstance();

    helper_traverseIterable($domStep::root()->invokeStep(new Input($html)));
});

test('ResponseInterface is a valid input', function () {
    $response = new Response();

    $domStep = helper_getDomStepInstance();

    helper_traverseIterable($domStep::root()->invokeStep(new Input($response)));
});

test('RequestResponseAggregate is a valid input', function () {
    $aggregate = new RequestResponseAggregate(new Request('GET', '/'), new Response());

    $domStep = helper_getDomStepInstance();

    helper_traverseIterable($domStep::root()->invokeStep(new Input($aggregate)));
});

test('For other inputs an InvalidArgumentException is thrown', function (mixed $input) {
    $domStep = helper_getDomStepInstance();

    helper_traverseIterable($domStep::root()->invokeStep(new Input($input)));
})->throws(InvalidArgumentException::class)->with([123, 123.456, new stdClass()]);

it('extracts one result from the root node when the root method is used', function () {
    $html = helper_getStepFilesContent('Html/basic.html');

    $domStep = helper_getDomStepInstance();

    $output = helper_generatorToArray(
        $domStep::root()->extract(['matches' => '.match'])->invokeStep(new Input($html))
    );

    expect($output)->toHaveCount(1);

    expect($output[0]->get())->toBe(['matches' => ['match 1', 'match 2', 'match 3']]);
});

it('extracts each matching result when the each method is used', function () {
    $html = helper_getStepFilesContent('Html/basic.html');

    $domStep = helper_getDomStepInstance();

    $output = helper_generatorToArray(
        $domStep::each('.list .item')->extract(['match' => '.match'])->invokeStep(new Input($html))
    );

    expect($output)->toHaveCount(2);

    expect($output[0]->get())->toBe(['match' => 'match 2']);

    expect($output[1]->get())->toBe(['match' => 'match 3']);
});

it('extracts the first matching result when the first method is used', function () {
    $html = helper_getStepFilesContent('Html/basic.html');

    $domStep = helper_getDomStepInstance();

    $output = helper_generatorToArray(
        $domStep::first('.list .item')->extract(['match' => '.match'])->invokeStep(new Input($html))
    );

    expect($output)->toHaveCount(1);

    expect($output[0]->get())->toBe(['match' => 'match 2']);
});

it('extracts the last matching result when the last method is used', function () {
    $html = helper_getStepFilesContent('Html/basic.html');

    $domStep = helper_getDomStepInstance();

    $output = helper_generatorToArray(
        $domStep::last('.list .item')->extract(['match' => '.match'])->invokeStep(new Input($html))
    );

    expect($output)->toHaveCount(1);

    expect($output[0]->get())->toBe(['match' => 'match 3']);
});

test('The static cssSelector method returns an instance of CssSelector using the provided selector', function () {
    $cssSelector = Dom::cssSelector('.item');

    expect($cssSelector)->toBeInstanceOf(CssSelector::class);

    $itemContent = $cssSelector->apply(new Crawler('<span class="item">yes</span>'));

    expect($itemContent)->toBe('yes');
});

test('The static xPath method returns an instance of XPathQuery using the provided query', function () {
    $xPathQuery = Dom::xPath('//item');

    expect($xPathQuery)->toBeInstanceOf(XPathQuery::class);

    $itemContent = $xPathQuery->apply(new Crawler('<item>yes</item>'));

    expect($itemContent)->toBe('yes');
});

it('uses the keys of the provided mapping as keys in the returned output', function () {
    $html = '<p class="foo">foo content</p><p class="bar">bar content</p><p class="baz">baz content</p>';

    $domStep = helper_getDomStepInstance();

    $output = helper_generatorToArray(
        $domStep::root()->extract(['foo' => '.foo', 'notBar' => '.bar', '.baz'])->invokeStep(new Input($html))
    );

    expect($output)->toHaveCount(1);

    expect($output[0]->get())->toBe(['foo' => 'foo content', 'notBar' => 'bar content', 0 => 'baz content']);
});

it('trims the extracted data', function () {
    $html = "<p class=\"foo\">  \n   foo content   \n   \n</p>";

    $domStep = helper_getDomStepInstance();

    $output = helper_generatorToArray(
        $domStep::root()->extract(['foo' => '.foo'])->invokeStep(new Input($html))
    );

    expect($output)->toHaveCount(1);

    expect($output[0]->get())->toBe(['foo' => 'foo content']);
});
