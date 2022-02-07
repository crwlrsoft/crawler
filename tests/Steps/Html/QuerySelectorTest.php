<?php

namespace tests\Steps\Html;

use Crwlr\Crawler\Aggregates\RequestResponseAggregate;
use Crwlr\Crawler\Input;
use Crwlr\Crawler\Logger\CliLogger;
use Crwlr\Crawler\Steps\Html\QuerySelector;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Utils;

test('Can be created with selector', function () {
    $querySelectorStep = new QuerySelector('p.someCssClass');
    expect($querySelectorStep)->toBeInstanceOf(QuerySelector::class);
});

test('It works with string as input', function () {
    $inputString = <<<HTML
<div id="selectThis">yep</div><div>not this</div>
HTML;
    $querySelectorStep = new QuerySelector('#selectThis');
    $querySelectorStep->addLogger(new CliLogger());
    $input = new Input($inputString);
    $results = $querySelectorStep->invokeStep($input);
    expect($results)->toHaveCount(1);
    $firstResult = reset($results);
    expect($firstResult->get())->toBe('yep');
});

test('It works with PSR-7 Response object as input', function () {
    $html = <<<HTML
<div id="selectThis">yep</div><div>not this</div>
HTML;
    $responseObject = new Response(body: Utils::streamFor($html));
    $querySelectorStep = new QuerySelector('#selectThis');
    $querySelectorStep->addLogger(new CliLogger());
    $input = new Input($responseObject);
    $results = $querySelectorStep->invokeStep($input);
    $result = reset($results);
    expect($result->get())->toBe('yep');
});

test('It works with a RequestResponseAggregate object as input', function () {
    $html = <<<HTML
<div id="selectThis">yep</div><div>not this</div>
HTML;
    $requestObject = new Request('GET', '/');
    $responseObject = new Response(body: Utils::streamFor($html));
    $aggregate = new RequestResponseAggregate($requestObject, $responseObject);
    $querySelectorStep = new QuerySelector('#selectThis');
    $querySelectorStep->addLogger(new CliLogger());
    $input = new Input($aggregate);
    $results = $querySelectorStep->invokeStep($input);
    $result = reset($results);
    expect($result->get())->toBe('yep');
});

test('You can get the (inner) html of an element', function ($setGetWhatVia) {
    $html = <<<HTML
<div id="selectThis"><p>this is a paragraph</p></div><div>not this</div>
HTML;

    if ($setGetWhatVia === 'argument') {
        $querySelectorStep = new QuerySelector('#selectThis', 'html');
    } else {
        $querySelectorStep = new QuerySelector('#selectThis');
        $querySelectorStep->html();
    }

    $querySelectorStep->addLogger(new CliLogger());
    $input = new Input($html);
    $results = $querySelectorStep->invokeStep($input);
    $result = reset($results);
    expect($result->get())->toBe('<p>this is a paragraph</p>');
})->with(['argument', 'methodCall']);

test('You can get the outer html of an element', function ($setGetWhatVia) {
    $html = <<<HTML
<div id="selectThis">yo</div><div>not this</div>
HTML;

    if ($setGetWhatVia === 'argument') {
        $querySelectorStep = new QuerySelector('#selectThis', 'outerHtml');
    } else {
        $querySelectorStep = new QuerySelector('#selectThis');
        $querySelectorStep->outerHtml();
    }

    $querySelectorStep->addLogger(new CliLogger());
    $input = new Input($html);
    $results = $querySelectorStep->invokeStep($input);
    $result = reset($results);
    expect($result->get())->toBe('<div id="selectThis">yo</div>');
})->with(['argument', 'methodCall']);

test('You can get the text of an element', function ($setGetWhatVia) {
    $html = <<<HTML
<div id="selectThis">one <span>two</span></div><div>not this</div>
HTML;

    if ($setGetWhatVia === 'argument') {
        $querySelectorStep = new QuerySelector('#selectThis', 'text');
    } else {
        $querySelectorStep = new QuerySelector('#selectThis');
        $querySelectorStep->text();
    }

    $querySelectorStep->addLogger(new CliLogger());
    $input = new Input($html);
    $results = $querySelectorStep->invokeStep($input);
    $result = reset($results);
    expect($result->get())->toBe('one two');
})->with(['argument', 'methodCall']);

test('You can get the inner text of an element', function ($setGetWhatVia) {
    $html = <<<HTML
<div id="selectThis">one <span>two</span></div><div>not this</div>
HTML;

    if ($setGetWhatVia === 'argument') {
        $querySelectorStep = new QuerySelector('#selectThis', 'innerText');
    } else {
        $querySelectorStep = new QuerySelector('#selectThis');
        $querySelectorStep->innerText();
    }

    $querySelectorStep->addLogger(new CliLogger());
    $input = new Input($html);
    $results = $querySelectorStep->invokeStep($input);
    $result = reset($results);
    expect($result->get())->toBe('one');
})->with(['argument', 'methodCall']);

test('You can get an attribute of an element', function () {
    $html = <<<HTML
<div data-foo="bar" id="selectThis">Example</div>
HTML;
    $querySelectorStep = new QuerySelector('#selectThis');
    $querySelectorStep->attribute('data-foo');
    $querySelectorStep->addLogger(new CliLogger());
    $input = new Input($html);
    $results = $querySelectorStep->invokeStep($input);
    $result = reset($results);
    expect($result->get())->toBe('bar');
});

test('It gets the first element when multiple elements match', function () {
    $html = <<<HTML
<div class="element">foo</div><div class="element">bar</div><div class="element">baz</div>
HTML;
    $querySelectorStep = new QuerySelector('.element');
    $querySelectorStep->addLogger(new CliLogger());
    $input = new Input($html);
    $results = $querySelectorStep->invokeStep($input);
    $result = reset($results);
    expect($result->get())->toBe('foo');
});
