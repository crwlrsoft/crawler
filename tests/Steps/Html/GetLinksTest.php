<?php

namespace tests\Steps\Html;

use Crwlr\Crawler\Aggregates\RequestResponseAggregate;
use Crwlr\Crawler\Input;
use Crwlr\Crawler\Logger\CliLogger;
use Crwlr\Crawler\Steps\Html\GetLinks;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use InvalidArgumentException;
use stdClass;

test('It works with a RequestResponseAggregate as input', function () {
    $step = new GetLinks();
    $step->addLogger(new CliLogger());
    $links = $step->invokeStep(new Input(
        new RequestResponseAggregate(
            new Request('GET', 'https://www.example.com/home'),
            new Response(200, [], '<a href="/blog">link</a>')
        )
    ));

    expect($links)->toBeArray();
    expect($links)->toHaveCount(1);
    $link = reset($links)->get(); // @phpstan-ignore-line
    expect($link)->toBe('https://www.example.com/blog');
});

test('It does not work with something else as input', function () {
    $step = new GetLinks();
    $step->addLogger(new CliLogger());
    $step->invokeStep(new Input(new stdClass()));
})->throws(InvalidArgumentException::class);

test('When passing a CSS selector it only selects matching links', function () {
    $step = new GetLinks('.matchingLink');
    $step->addLogger(new CliLogger());
    $links = $step->invokeStep(new Input(
        new RequestResponseAggregate(
            new Request('GET', 'https://www.example.com/company/about'),
            new Response(
                200,
                [],
                <<<HTML
<div>
    <a class="matchingLink" href="jobs">Jobs</a>
    <a class="matchingLink" href="numbers">Numbers</a>
    <a class="notMatchingLink" href="/products">Products</a>
    <a class="matchingLink" href="/team">Team</a>
</div>
HTML
            )
        )
    ));

    expect($links)->toBeArray();
    expect($links)->toHaveCount(3);
    expect(reset($links)->get())->toBe('https://www.example.com/company/jobs'); // @phpstan-ignore-line
    expect(next($links)->get())->toBe('https://www.example.com/company/numbers'); // @phpstan-ignore-line
    expect(next($links)->get())->toBe('https://www.example.com/team'); // @phpstan-ignore-line
});

test('When selector matches on a non-link element it\'s ignored', function () {
    $step = new GetLinks('.link');
    $step->addLogger(new CliLogger());
    $links = $step->invokeStep(new Input(
        new RequestResponseAggregate(
            new Request('GET', 'https://www.otsch.codes'),
            new Response(200, [], '<a class="link" href="/foo">Foo</a><span class="link">Bar</span>')
        )
    ));

    expect($links)->toBeArray();
    expect($links)->toHaveCount(1);
    expect(reset($links)->get())->toBe('https://www.otsch.codes/foo'); // @phpstan-ignore-line
});
