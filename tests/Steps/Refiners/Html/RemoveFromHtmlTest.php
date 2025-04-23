<?php

namespace tests\Steps\Refiners\DateTime;

use Crwlr\Crawler\Steps\Dom;
use Crwlr\Crawler\Steps\Refiners\HtmlRefiner;

it('removes a certain node from an HTML document by selector', function () {
    $html = <<<HTML
        <!doctype html>
        <html>
        <head></head>
        <body>
        <h1>Hi!</h1>
        <div id="foo">remove this!</div>
        </body>
        </html>
        HTML;

    $refinedValue = HtmlRefiner::remove('#foo')->refine($html);

    expect($refinedValue)->not()->toContain('remove this!')
        ->and($refinedValue)->toContain('<h1>Hi!</h1>');
});

it('removes a certain node from an HTML snippet by selector', function () {
    $html = <<<HTML
        <article>
        <h1>Hi!</h1>
        <p id="foo">remove this!</p>
        </article>
        HTML;

    $refinedValue = HtmlRefiner::remove('#foo')->refine($html);

    expect($refinedValue)->not()->toContain('remove this!')
        ->and($refinedValue)->toContain('<h1>Hi!</h1>')
        ->and($refinedValue)->not()->toContain('<html>');
});

it('removes multiple nodes from an HTML snippet by selector', function () {
    $html = <<<HTML
        <article>
        <ul id="list">
            <li>foo</li>
            <li class="remove">bar</li>
            <li>baz</li>
            <li class="remove">quz</li>
        </ul>
        </article>
        HTML;

    $refinedValue = HtmlRefiner::remove('#list .remove')->refine($html);

    expect($refinedValue)->not()->toContain('bar')
        ->and($refinedValue)->not()->toContain('quz')
        ->and($refinedValue)->toContain('<li>foo</li>')
        ->and($refinedValue)->toContain('<li>baz</li>')
        ->and($refinedValue)->not()->toContain('<html>');
});

it('removes multiple nodes from HTML by xpath query', function () {
    $html = <<<HTML
        <article>
        <ul id="list">
            <li>foo</li>
            <li class="remove">bar</li>
            <li>baz</li>
            <li class="remove">quz</li>
        </ul>
        </article>
        HTML;

    $refinedValue = HtmlRefiner::remove(Dom::xPath('//li[contains(@class, \'remove\')]'))->refine($html);

    expect($refinedValue)->not()->toContain('bar')
        ->and($refinedValue)->not()->toContain('quz')
        ->and($refinedValue)->toContain('<li>foo</li>')
        ->and($refinedValue)->toContain('<li>baz</li>')
        ->and($refinedValue)->not()->toContain('<html>');
});

it('removes node from an array of HTML snippets', function () {
    $html = [
        <<<HTML
        <ul id="list">
            <li>foo</li>
            <li class="remove">bar</li>
            <li>baz</li>
            <li class="remove">quz</li>
        </ul>
        HTML,
        <<<HTML
        <ul id="list">
            <li>lorem</li>
            <li class="remove">ipsum</li>
            <li>dolor</li>
            <li class="remove">sit</li>
        </ul>
        HTML,
    ];

    $refinedValue = HtmlRefiner::remove('.remove')->refine($html);

    expect($refinedValue[0])->not()->toContain('bar')
        ->and($refinedValue[0])->not()->toContain('quz')
        ->and($refinedValue[1])->not()->toContain('ipsum')
        ->and($refinedValue[1])->not()->toContain('sit');
});
