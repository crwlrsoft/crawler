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
