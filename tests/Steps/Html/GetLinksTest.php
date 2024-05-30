<?php

namespace tests\Steps\Html;

use Crwlr\Crawler\Loader\Http\Messages\RespondedRequest;
use Crwlr\Crawler\Input;
use Crwlr\Crawler\Steps\Html\GetLinks;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use InvalidArgumentException;
use stdClass;

use function tests\helper_invokeStepWithInput;
use function tests\helper_traverseIterable;

it('works with a RespondedRequest as input', function () {
    $step = (new GetLinks());

    $links = helper_invokeStepWithInput($step, new RespondedRequest(
        new Request('GET', 'https://www.example.com/home'),
        new Response(200, [], '<a href="/blog">link</a>'),
    ));

    expect($links)->toHaveCount(1);

    expect($links[0]->get())->toBe('https://www.example.com/blog');
});

it('does not work with something else as input', function () {
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
            '<div><a href="v0.1">v0.1</a><a href="v1.0">v1.0</a><a href="v1.1">v1.1</a></div>',
        ),
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
        new Response(200, [], $responseHtml),
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
        new Response(200, [], '<a class="link" href="foo">Foo</a><span class="link">Bar</span>'),
    ));

    expect($links)->toHaveCount(1);

    expect($links[0]->get())->toBe('https://www.otsch.codes/foo');
});

it('finds only links on the same domain when onSameDomain() was called', function () {
    $html = <<<HTML
        <a href="https://www.crwlr.software/packages">link1</a>
        <a href="https://blog.otsch.codes/articles">link2</a>
        <a href="https://www.otsch.codes/blog">link3</a>
        HTML;

    $step = (new GetLinks())->onSameDomain();

    $link = helper_invokeStepWithInput($step, new RespondedRequest(
        new Request('GET', 'https://www.otsch.codes'),
        new Response(200, [], $html),
    ));

    expect($link)->toHaveCount(2);

    expect($link[0]->get())->toBe('https://blog.otsch.codes/articles');

    expect($link[1]->get())->toBe('https://www.otsch.codes/blog');
});

it('doesn\'t find links on the same domain when notOnSameDomain() was called', function () {
    $html = <<<HTML
        <a href="https://www.otsch.codes/contact">link1</a>
        <a href="https://www.crwlr.software/packages">link2</a>
        <a href="https://www.example.com/foo">link3</a>
        HTML;

    $step = (new GetLinks())->notOnSameDomain();

    $link = helper_invokeStepWithInput($step, new RespondedRequest(
        new Request('GET', 'https://www.otsch.codes'),
        new Response(200, [], $html),
    ));

    expect($link)->toHaveCount(2);

    expect($link[0]->get())->toBe('https://www.crwlr.software/packages');

    expect($link[1]->get())->toBe('https://www.example.com/foo');
});

it('finds only links from domains the onDomain() method was called with', function () {
    $html = <<<HTML
        <a href="https://www.otsch.codes/contact">link1</a>
        <a href="https://www.crwlr.software/packages">link2</a>
        <a href="https://www.crwl.io">link3</a>
        <a href="https://www.crwlr.software/blog">link4</a>
        HTML;

    $step = (new GetLinks())->onDomain('crwlr.software');

    $links = helper_invokeStepWithInput($step, new RespondedRequest(
        new Request('GET', 'https://www.otsch.codes'),
        new Response(200, [], $html),
    ));

    expect($links)->toHaveCount(2);

    expect($links[0]->get())->toBe('https://www.crwlr.software/packages');

    expect($links[1]->get())->toBe('https://www.crwlr.software/blog');
});

test('onDomain() also takes an array of domains', function () {
    $html = <<<HTML
        <a href="https://www.otsch.codes/contact">link1</a>
        <a href="https://www.crwlr.software/packages">link2</a>
        <a href="https://www.example.com/yolo">link3</a>
        HTML;

    $step = (new GetLinks())->onDomain(['otsch.codes', 'crwlr.software']);

    $links = helper_invokeStepWithInput($step, new RespondedRequest(
        new Request('GET', 'https://www.otsch.codes'),
        new Response(200, [], $html),
    ));

    expect($links)->toHaveCount(2);

    expect($links[0]->get())->toBe('https://www.otsch.codes/contact');

    expect($links[1]->get())->toBe('https://www.crwlr.software/packages');
});

test('onDomain() can be called multiple times and merges all domains it was called with', function () {
    $html = <<<HTML
        <a href="https://www.otsch.codes/contact">link1</a>
        <a href="https://www.crwlr.software/packages">link2</a>
        <a href="https://www.example.com/yolo">link3</a>
        HTML;

    $step = (new GetLinks())->onDomain('crwl.io');

    $links = helper_invokeStepWithInput($step, new RespondedRequest(
        new Request('GET', 'https://www.otsch.codes'),
        new Response(200, [], $html),
    ));

    expect($links)->toHaveCount(0);

    $step->onDomain(['otsch.codes', 'crwlr.software']);

    $links = helper_invokeStepWithInput($step, new RespondedRequest(
        new Request('GET', 'https://www.otsch.codes'),
        new Response(200, [], $html),
    ));

    expect($links)->toHaveCount(2);

    $step->onDomain('example.com');

    $links = helper_invokeStepWithInput($step, new RespondedRequest(
        new Request('GET', 'https://www.otsch.codes'),
        new Response(200, [], $html),
    ));

    expect($links)->toHaveCount(3);
});

it('finds only links on the same host when onSameHost() was called', function () {
    $html = <<<HTML
        <a href="https://www.crwlr.software/packages">link1</a>
        <a href="https://www.otsch.codes/contact">link2</a>
        <a href="https://jobs.otsch.codes">link3</a>
        <a href="https://www.otsch.codes/blog">link4</a>
        HTML;

    $step = (new GetLinks())->onSameHost();

    $link = helper_invokeStepWithInput($step, new RespondedRequest(
        new Request('GET', 'https://www.otsch.codes'),
        new Response(200, [], $html),
    ));

    expect($link)->toHaveCount(2);

    expect($link[0]->get())->toBe('https://www.otsch.codes/contact');

    expect($link[1]->get())->toBe('https://www.otsch.codes/blog');
});

it('doesn\'t find links on the same host when notOnSameHost() was called', function () {
    $html = <<<HTML
        <a href="https://www.otsch.codes/contact">link1</a>
        <a href="https://jobs.otsch.codes">link2</a>
        <a href="https://www.crwlr.software/packages">link3</a>
        HTML;

    $step = (new GetLinks())->notOnSameHost();

    $link = helper_invokeStepWithInput($step, new RespondedRequest(
        new Request('GET', 'https://www.otsch.codes'),
        new Response(200, [], $html),
    ));

    expect($link)->toHaveCount(2);

    expect($link[0]->get())->toBe('https://jobs.otsch.codes');

    expect($link[1]->get())->toBe('https://www.crwlr.software/packages');
});

it('finds only links from hosts the onHost() method was called with', function () {
    $html = <<<HTML
        <a href="https://www.otsch.codes/contact">link1</a>
        <a href="https://www.crwlr.software/packages">link2</a>
        <a href="https://blog.crwlr.software">link3</a>
        <a href="https://www.crwlr.software/packages/crawler/v0.4/getting-started">link4</a>
        HTML;

    $step = (new GetLinks())->onHost('www.crwlr.software');

    $links = helper_invokeStepWithInput($step, new RespondedRequest(
        new Request('GET', 'https://www.otsch.codes'),
        new Response(200, [], $html),
    ));

    expect($links)->toHaveCount(2);

    expect($links[0]->get())->toBe('https://www.crwlr.software/packages');

    expect($links[1]->get())->toBe('https://www.crwlr.software/packages/crawler/v0.4/getting-started');
});

test('onHost() also takes an array of hosts', function () {
    $html = <<<HTML
        <a href="https://www.otsch.codes/contact">link1</a>
        <a href="https://www.crwlr.software/packages">link2</a>
        HTML;

    $step = (new GetLinks())->onHost(['www.otsch.codes', 'blog.example.com']);

    $links = helper_invokeStepWithInput($step, new RespondedRequest(
        new Request('GET', 'https://www.otsch.codes'),
        new Response(200, [], $html),
    ));

    expect($links)->toHaveCount(1);

    expect($links[0]->get())->toBe('https://www.otsch.codes/contact');

    $html = <<<HTML
        <a href="https://www.otsch.codes/contact">link1</a>
        <a href="https://www.crwlr.software/packages">link2</a>
        <a href="https://blog.example.com/articles/1">link3</a>
        HTML;

    $links = helper_invokeStepWithInput($step, new RespondedRequest(
        new Request('GET', 'https://www.otsch.codes'),
        new Response(200, [], $html),
    ));

    expect($links)->toHaveCount(2);

    expect($links[1]->get())->toBe('https://blog.example.com/articles/1');
});

test('onHost() can be called multiple times and merges all hosts it was called with', function () {
    $html = <<<HTML
        <a href="https://www.otsch.codes/contact">link1</a>
        HTML;

    $step = (new GetLinks())->onHost('www.crwl.io');

    $links = helper_invokeStepWithInput($step, new RespondedRequest(
        new Request('GET', 'https://www.otsch.codes'),
        new Response(200, [], $html),
    ));

    expect($links)->toHaveCount(0);

    $step->onHost(['www.otsch.codes', 'www.crwlr.software']);

    $html = <<<HTML
        <a href="https://www.crwl.io">link1</a>
        HTML;

    $links = helper_invokeStepWithInput($step, new RespondedRequest(
        new Request('GET', 'https://www.otsch.codes'),
        new Response(200, [], $html),
    ));

    expect($links)->toHaveCount(1);

    expect($links[0]->get())->toBe('https://www.crwl.io');

    $html = <<<HTML
        <a href="https://www.otsch.codes/blog">link1</a>
        <a href="https://www.crwl.io">link2</a>
        HTML;

    $links = helper_invokeStepWithInput($step, new RespondedRequest(
        new Request('GET', 'https://www.otsch.codes'),
        new Response(200, [], $html),
    ));

    expect($links)->toHaveCount(2);

    expect($links[0]->get())->toBe('https://www.otsch.codes/blog');

    expect($links[1]->get())->toBe('https://www.crwl.io');
});

it('works correctly when HTML contains a base tag', function () {
    $html = <<<HTML
        <!DOCTYPE html>
        <html>
        <head>
        <base href="/c/d" />
        </head>
        <body>
        <a href="e">link</a>
        <a href="/f/g">link2</a>
        <a href="./h">link3</a>
        </body>
        </html>
        HTML;

    $step = (new GetLinks());

    $links = helper_invokeStepWithInput($step, new RespondedRequest(
        new Request('GET', 'https://www.example.com/a/b'),
        new Response(200, [], $html),
    ));

    expect($links[0]->get())->toBe('https://www.example.com/c/e');

    expect($links[1]->get())->toBe('https://www.example.com/f/g');

    expect($links[2]->get())->toBe('https://www.example.com/c/h');
});

it('throws away the URL fragment part when withoutFragment() was called', function () {
    $html = <<<HTML
        <!DOCTYPE html>
        <html>
        <head></head>
        <body>
            <a href="/foo/bar#fragment">link</a> <br>
            <a href="/baz#quz-fragment">another link</a> <br>
        </body>
        </html>
        HTML;

    $step = (new GetLinks());

    $respondedRequest = new RespondedRequest(
        new Request('GET', 'https://www.example.com/foo/baz'),
        new Response(200, [], $html),
    );

    $links = helper_invokeStepWithInput($step, $respondedRequest);

    expect($links[0]->get())->toBe('https://www.example.com/foo/bar#fragment');

    expect($links[1]->get())->toBe('https://www.example.com/baz#quz-fragment');

    $step->withoutFragment();

    $links = helper_invokeStepWithInput($step, $respondedRequest);

    expect($links[0]->get())->toBe('https://www.example.com/foo/bar');

    expect($links[1]->get())->toBe('https://www.example.com/baz');
});

it('ignores special non HTTP links', function () {
    $html = <<<HTML
        <!DOCTYPE html>
        <html>
        <head></head>
        <body>
        <a href="mailto:somebody@example.com">mailto link</a>
        <a href="/one">link one</a>
        <a href="javascript:alert('hello');">javascript link</a>
        <a href="/two">link two</a>
        <a href="tel:+499123456789">phone link</a>
        <a href="/three">link three</a>
        </body>
        </html>
        HTML;

    $step = (new GetLinks());

    $respondedRequest = new RespondedRequest(
        new Request('GET', 'https://www.example.com/home'),
        new Response(200, [], $html),
    );

    $links = helper_invokeStepWithInput($step, $respondedRequest);

    expect($links)->toHaveCount(3);

    expect($links[0]->get())->toBe('https://www.example.com/one');

    expect($links[1]->get())->toBe('https://www.example.com/two');

    expect($links[2]->get())->toBe('https://www.example.com/three');
});
