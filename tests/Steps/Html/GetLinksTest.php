<?php

namespace tests\Steps\Html;

use Crwlr\Crawler\Loader\Http\Messages\RespondedRequest;
use Crwlr\Crawler\Input;
use Crwlr\Crawler\Logger\CliLogger;
use Crwlr\Crawler\Steps\Html\GetLinks;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use InvalidArgumentException;
use stdClass;
use function tests\helper_generatorToArray;
use function tests\helper_invokeStepWithInput;
use function tests\helper_traverseIterable;

test('It works with a RequestResponseAggregate as input', function () {
    $step = (new GetLinks());

    $links = helper_invokeStepWithInput($step, new RespondedRequest(
        new Request('GET', 'https://www.example.com/home'),
        new Response(200, [], '<a href="/blog">link</a>')
    ));

    expect($links)->toHaveCount(1);

    expect($links[0]->get())->toBe('https://www.example.com/blog');
});

test('It does not work with something else as input', function () {
    $step = new GetLinks();

    helper_traverseIterable($step->invokeStep(new Input(new stdClass())));
})->throws(InvalidArgumentException::class);

test('When called without selector it just gets all links', function () {
    $step = (new GetLinks());

    $links = helper_invokeStepWithInput($step, new RespondedRequest(
        new Request('GET', 'https://www.crwlr.software/packages/url/'),
        new Response(
            200,
            [],
            '<div><a href="v0.1">v0.1</a><a href="v1.0">v1.0</a><a href="v1.1">v1.1</a></div>'
        )
    ));

    expect($links[0]->get())->toBe('https://www.crwlr.software/packages/url/v0.1');

    expect($links[1]->get())->toBe('https://www.crwlr.software/packages/url/v1.0');

    expect($links[2]->get())->toBe('https://www.crwlr.software/packages/url/v1.1');
});

test('When passing a CSS selector it only selects matching links', function () {
    $step = (new GetLinks('.matchingLink'));

    $responseHtml = <<<HTML
        <div>
            <a class="matchingLink" href="jobs">Jobs</a>
            <a class="matchingLink" href="numbers">Numbers</a>
            <a class="notMatchingLink" href="/products">Products</a>
            <a class="matchingLink" href="/team">Team</a>
        </div>
        HTML;

    $links = helper_invokeStepWithInput($step, new RespondedRequest(
        new Request('GET', 'https://www.example.com/company/about'),
        new Response(200, [], $responseHtml)
    ));

    expect($links)->toHaveCount(3);

    expect(reset($links)->get())->toBe('https://www.example.com/company/jobs'); // @phpstan-ignore-line

    expect(next($links)->get())->toBe('https://www.example.com/company/numbers'); // @phpstan-ignore-line

    expect(next($links)->get())->toBe('https://www.example.com/team'); // @phpstan-ignore-line
});

test('When selector matches on a non-link element it\'s ignored', function () {
    $step = (new GetLinks('.link'));

    $links = helper_invokeStepWithInput($step, new RespondedRequest(
        new Request('GET', 'https://www.otsch.codes'),
        new Response(200, [], '<a class="link" href="foo">Foo</a><span class="link">Bar</span>')
    ));

    expect($links)->toHaveCount(1);

    expect($links[0]->get())->toBe('https://www.otsch.codes/foo');
});
