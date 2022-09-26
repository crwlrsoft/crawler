<?php

namespace tests\Steps\Html;

use Crwlr\Crawler\Steps\Html;
use Crwlr\Crawler\Steps\Html\MetaData;

use function tests\helper_invokeStepWithInput;

it('returns an array with key title and empty string if the HTML document doesn\'t even contain a title', function () {
    $html = <<<HTML
        <!DOCTYPE html>
        <html>
        <head>
        </head>
        <body>Hello World!</body>
        </html>
        HTML;

    $outputs = helper_invokeStepWithInput(new MetaData(), $html);

    expect($outputs[0]->get())->toBe(['title' => '']);
});

it('returns an array with the title and all meta tags having a name or property attribute', function () {
    $html = <<<HTML
        <!DOCTYPE html>
        <html>
        <head>
        <meta charset="UTF-8">
        <title>
            Hello World!
        </title>
        <meta name="description" content="This is a page saying: Hello World!" />
        <meta name="keywords" content="lorem, ipsum, hello, world" />
        <meta property="og:title" content="Hello World!" />
        <meta property="og:type" content="website" />
        </head>
        <body>Hello World!</body>
        </html>
        HTML;

    $outputs = helper_invokeStepWithInput(new MetaData(), $html);

    expect($outputs[0]->get())->toBe([
        'title' => 'Hello World!',
        'description' => 'This is a page saying: Hello World!',
        'keywords' => 'lorem, ipsum, hello, world',
        'og:title' => 'Hello World!',
        'og:type' => 'website',
    ]);
});

it('returns only the meta tags defined via the only() method', function () {
    $html = <<<HTML
        <!DOCTYPE html>
        <html>
        <head>
        <meta charset="UTF-8">
        <title>
            Hello World!
        </title>
        <meta name="description" content="This is a page saying: Hello World!" />
        <meta name="keywords" content="lorem, ipsum, hello, world" />
        <meta property="og:title" content="Hello World!" />
        <meta property="og:type" content="website" />
        </head>
        <body>Hello World!</body>
        </html>
        HTML;

    $outputs = helper_invokeStepWithInput(Html::metaData()->only(['title', 'description']), $html);

    expect($outputs[0]->get())->toBe([
        'title' => 'Hello World!',
        'description' => 'This is a page saying: Hello World!',
    ]);
});
