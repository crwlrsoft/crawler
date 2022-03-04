<?php

namespace tests\Steps\Html;

use Crwlr\Crawler\Aggregates\RequestResponseAggregate;
use Crwlr\Crawler\Input;
use Crwlr\Crawler\Logger\CliLogger;
use Crwlr\Crawler\Steps\Html\GetLink;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use InvalidArgumentException;
use function tests\helper_generatorToArray;
use function tests\helper_traverseIterable;

test('It works with a RequestResponseAggregate as input', function () {
    $step = new GetLink();
    $step->addLogger(new CliLogger());
    $link = helper_generatorToArray($step->invokeStep(new Input(
        new RequestResponseAggregate(
            new Request('GET', 'https://www.crwl.io/foo/bar'),
            new Response(200, [], '<a href="/blog">link</a>')
        )
    )));

    expect($link[0]->get())->toBe('https://www.crwl.io/blog');
});

test('It does not work with something else as input', function () {
    $step = new GetLink();
    $step->addLogger(new CliLogger());
    helper_traverseIterable($step->invokeStep(new Input(new Response())));
})->throws(InvalidArgumentException::class);

test('When called without selector it just returns the first link', function () {
    $step = new GetLink();
    $step->addLogger(new CliLogger());
    $link = $step->invokeStep(new Input(
        new RequestResponseAggregate(
            new Request('GET', 'https://www.crwlr.software/packages/url/'),
            new Response(
                200,
                [],
                '<div><a href="v0.1">v0.1</a><a href="v1.0">v1.0</a><a href="v1.1">v1.1</a></div>'
            )
        )
    ));

    $link = helper_generatorToArray($link);
    expect($link[0]->get())->toBe('https://www.crwlr.software/packages/url/v0.1');
});

test('When passing a CSS selector it selects the first matching link', function () {
    $step = new GetLink('.matchingLink');
    $step->addLogger(new CliLogger());
    $link = $step->invokeStep(new Input(
        new RequestResponseAggregate(
            new Request('GET', 'https://www.foo.bar/company/about'),
            new Response(
                200,
                [],
                <<<HTML
<div>
    <a class="matchingLink" href="jobs">Jobs</a>
    <a class="matchingLink" href="numbers">Numbers</a>
    <a class="nonMatchingLink" href="/products">Products</a>
</div>
HTML
            )
        )
    ));

    $link = helper_generatorToArray($link);
    expect($link[0]->get())->toBe('https://www.foo.bar/company/jobs');
});

test('When selector matches on a non-link element it\'s ignored', function () {
    $step = new GetLink('.link');
    $step->addLogger(new CliLogger());
    $links = $step->invokeStep(new Input(
        new RequestResponseAggregate(
            new Request('GET', 'https://www.otsch.codes'),
            new Response(200, [], '<span class="link">not a link</span><a class="link" href="foo">link</a>')
        )
    ));

    $links = iterator_to_array($links);
    expect($links[0]->get())->toBe('https://www.otsch.codes/foo');
});
