<?php

namespace tests\Steps\Html;

use Crwlr\Crawler\Loader\Http\Messages\RespondedRequest;
use Crwlr\Crawler\Input;
use Crwlr\Crawler\Steps\Html\GetLink;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use tests\_Stubs\DummyLogger;

use function tests\helper_invokeStepWithInput;
use function tests\helper_traverseIterable;

it('works with a RespondedRequest as input', function () {
    $step = (new GetLink());

    $link = helper_invokeStepWithInput($step, new RespondedRequest(
        new Request('GET', 'https://www.crwl.io/foo/bar'),
        new Response(200, [], '<a href="/blog">link</a>'),
    ));

    expect($link)->toHaveCount(1)
        ->and($link[0]->get())->toBe('https://www.crwl.io/blog');
});

it('logs an error message when fed with invalid input', function () {
    $logger = new DummyLogger();

    $step = (new GetLink())->addLogger($logger);

    helper_traverseIterable($step->invokeStep(new Input(new Response())));

    expect($logger->messages)->not->toBeEmpty()
        ->and($logger->messages[0]['message'])->toBe(
            'The Crwlr\Crawler\Steps\Html\GetLink step was called with input that it can not work with: Input must ' .
            'be an instance of RespondedRequest.',
        );
});

test('When called without selector it just returns the first link', function () {
    $step = (new GetLink());

    $link = helper_invokeStepWithInput($step, new RespondedRequest(
        new Request('GET', 'https://www.crwlr.software/packages/url/'),
        new Response(
            200,
            [],
            '<div><a href="v0.1">v0.1</a><a href="v1.0">v1.0</a><a href="v1.1">v1.1</a></div>',
        ),
    ));

    expect($link[0]->get())->toBe('https://www.crwlr.software/packages/url/v0.1');
});

test('When passing a CSS selector it selects the first matching link', function () {
    $step = (new GetLink('.matchingLink'));

    $responseHtml = <<<HTML
        <div>
            <a class="matchingLink" href="jobs">Jobs</a>
            <a class="matchingLink" href="numbers">Numbers</a>
            <a class="nonMatchingLink" href="/products">Products</a>
        </div>
        HTML;

    $link = helper_invokeStepWithInput($step, new RespondedRequest(
        new Request('GET', 'https://www.foo.bar/company/about'),
        new Response(200, [], $responseHtml),
    ));

    expect($link[0]->get())->toBe('https://www.foo.bar/company/jobs');
});

test('When selector matches on a non-link element it\'s ignored', function () {
    $step = (new GetLink('.link'));

    $link = helper_invokeStepWithInput($step, new RespondedRequest(
        new Request('GET', 'https://www.otsch.codes'),
        new Response(200, [], '<span class="link">not a link</span><a class="link" href="foo">link</a>'),
    ));

    expect($link)->toHaveCount(1)
        ->and($link[0]->get())->toBe('https://www.otsch.codes/foo');
});

it('finds only links on the same domain when onSameDomain() was called', function () {
    $html = <<<HTML
        <a href="https://www.crwlr.software/packages">link1</a>
        <a href="https://blog.otsch.codes/articles">link2</a>
        HTML;

    $step = (new GetLink())->onSameDomain();

    $link = helper_invokeStepWithInput($step, new RespondedRequest(
        new Request('GET', 'https://www.otsch.codes'),
        new Response(200, [], $html),
    ));

    expect($link)->toHaveCount(1)
        ->and($link[0]->get())->toBe('https://blog.otsch.codes/articles');
});

it('doesn\'t find a link on the same domain when notOnSameDomain() was called', function () {
    $html = <<<HTML
        <a href="https://www.otsch.codes/contact">link1</a>
        <a href="https://www.crwlr.software/packages">link2</a>
        HTML;

    $step = (new GetLink())->notOnSameDomain();

    $link = helper_invokeStepWithInput($step, new RespondedRequest(
        new Request('GET', 'https://www.otsch.codes'),
        new Response(200, [], $html),
    ));

    expect($link)->toHaveCount(1)
        ->and($link[0]->get())->toBe('https://www.crwlr.software/packages');
});

it('finds only links from domains the onDomain() method was called with', function () {
    $html = <<<HTML
        <a href="https://www.otsch.codes/contact">link1</a>
        <a href="https://www.crwlr.software/packages">link2</a>
        <a href="https://www.crwl.io">link3</a>
        <a href="https://www.example.com">link4</a>
        HTML;

    $step = (new GetLink())->onDomain('example.com');

    $links = helper_invokeStepWithInput($step, new RespondedRequest(
        new Request('GET', 'https://www.otsch.codes'),
        new Response(200, [], $html),
    ));

    expect($links)->toHaveCount(1)
        ->and($links[0]->get())->toBe('https://www.example.com');
});

test('onDomain() also takes an array of domains', function () {
    $html = <<<HTML
        <a href="https://www.otsch.codes/contact">link1</a>
        <a href="https://www.crwlr.software/packages">link2</a>
        HTML;

    $step = (new GetLink())->onDomain(['otsch.codes', 'example.com']);

    $links = helper_invokeStepWithInput($step, new RespondedRequest(
        new Request('GET', 'https://www.otsch.codes'),
        new Response(200, [], $html),
    ));

    expect($links)->toHaveCount(1)
        ->and($links[0]->get())->toBe('https://www.otsch.codes/contact');

    $html = <<<HTML
        <a href="https://www.crwlr.software/packages">link1</a>
        <a href="https://www.example.com/foo">link2</a>
        HTML;

    $links = helper_invokeStepWithInput($step, new RespondedRequest(
        new Request('GET', 'https://www.otsch.codes'),
        new Response(200, [], $html),
    ));

    expect($links)->toHaveCount(1)
        ->and($links[0]->get())->toBe('https://www.example.com/foo');
});

test('onDomain() can be called multiple times and merges all domains it was called with', function () {
    $html = <<<HTML
        <a href="https://www.otsch.codes/contact">link1</a>
        HTML;

    $step = (new GetLink())->onDomain('crwl.io');

    $links = helper_invokeStepWithInput($step, new RespondedRequest(
        new Request('GET', 'https://www.otsch.codes'),
        new Response(200, [], $html),
    ));

    expect($links)->toHaveCount(0);

    $step->onDomain(['otsch.codes', 'crwlr.software']);

    $html = <<<HTML
        <a href="https://www.crwl.io">link1</a>
        <a href="https://www.otsch.codes/contact">link2</a>
        HTML;

    $links = helper_invokeStepWithInput($step, new RespondedRequest(
        new Request('GET', 'https://www.otsch.codes'),
        new Response(200, [], $html),
    ));

    expect($links)->toHaveCount(1)
        ->and($links[0]->get())->toBe('https://www.crwl.io');

    $html = <<<HTML
        <a href="https://www.otsch.codes/contact">link1</a>
        <a href="https://www.crwl.io">link2</a>
        HTML;

    $links = helper_invokeStepWithInput($step, new RespondedRequest(
        new Request('GET', 'https://www.otsch.codes'),
        new Response(200, [], $html),
    ));

    expect($links)->toHaveCount(1)
        ->and($links[0]->get())->toBe('https://www.otsch.codes/contact');
});

it('finds only links on the same host when onSameHost() was called', function () {
    $html = <<<HTML
        <a href="https://www.crwlr.software/packages">link1</a>
        <a href="https://jobs.otsch.codes">link2</a>
        <a href="https://www.otsch.codes/contact">link3</a>
        HTML;

    $step = (new GetLink())->onSameHost();

    $link = helper_invokeStepWithInput($step, new RespondedRequest(
        new Request('GET', 'https://www.otsch.codes'),
        new Response(200, [], $html),
    ));

    expect($link)->toHaveCount(1)
        ->and($link[0]->get())->toBe('https://www.otsch.codes/contact');
});

it('doesn\'t find a link on the same host when notOnSameHost() was called', function () {
    $html = <<<HTML
        <a href="https://www.otsch.codes/contact">link1</a>
        <a href="https://jobs.otsch.codes">link2</a>
        HTML;

    $step = (new GetLink())->notOnSameHost();

    $link = helper_invokeStepWithInput($step, new RespondedRequest(
        new Request('GET', 'https://www.otsch.codes'),
        new Response(200, [], $html),
    ));

    expect($link)->toHaveCount(1)
        ->and($link[0]->get())->toBe('https://jobs.otsch.codes');
});

it('finds only links from hosts the onHost() method was called with', function () {
    $html = <<<HTML
        <a href="https://www.otsch.codes/contact">link1</a>
        <a href="https://www.crwlr.software/packages">link2</a>
        <a href="https://www.crwl.io">link3</a>
        <a href="https://www.example.com">link4</a>
        HTML;

    $step = (new GetLink())->onHost('www.example.com');

    $links = helper_invokeStepWithInput($step, new RespondedRequest(
        new Request('GET', 'https://www.otsch.codes'),
        new Response(200, [], $html),
    ));

    expect($links)->toHaveCount(1)
        ->and($links[0]->get())->toBe('https://www.example.com');
});

test('onHost() also takes an array of hosts', function () {
    $html = <<<HTML
        <a href="https://www.otsch.codes/contact">link1</a>
        <a href="https://www.crwlr.software/packages">link2</a>
        HTML;

    $step = (new GetLink())->onHost(['www.otsch.codes', 'blog.example.com']);

    $links = helper_invokeStepWithInput($step, new RespondedRequest(
        new Request('GET', 'https://www.otsch.codes'),
        new Response(200, [], $html),
    ));

    expect($links)->toHaveCount(1)
        ->and($links[0]->get())->toBe('https://www.otsch.codes/contact');

    $html = <<<HTML
        <a href="https://www.example.com/foo">link1</a>
        <a href="https://www.crwlr.software/packages">link2</a>
        <a href="https://blog.example.com/articles/1">link3</a>
        HTML;

    $links = helper_invokeStepWithInput($step, new RespondedRequest(
        new Request('GET', 'https://www.otsch.codes'),
        new Response(200, [], $html),
    ));

    expect($links)->toHaveCount(1)
        ->and($links[0]->get())->toBe('https://blog.example.com/articles/1');
});

test('onHost() can be called multiple times and merges all hosts it was called with', function () {
    $html = <<<HTML
        <a href="https://www.otsch.codes/contact">link1</a>
        HTML;

    $step = (new GetLink())->onHost('www.crwl.io');

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

    expect($links)->toHaveCount(1)
        ->and($links[0]->get())->toBe('https://www.crwl.io');

    $html = <<<HTML
        <a href="https://www.otsch.codes/blog">link1</a>
        <a href="https://www.crwl.io">link2</a>
        HTML;

    $links = helper_invokeStepWithInput($step, new RespondedRequest(
        new Request('GET', 'https://www.otsch.codes'),
        new Response(200, [], $html),
    ));

    expect($links)->toHaveCount(1)
        ->and($links[0]->get())->toBe('https://www.otsch.codes/blog');
});

it('works correctly when HTML contains a base tag', function () {
    $html = <<<HTML
        <!DOCTYPE html>
        <html>
        <head>
        <base href="/c/d" />
        </head>
        <body><a href="e">link</a></body>
        </html>
        HTML;

    $step = (new GetLink());

    $links = helper_invokeStepWithInput($step, new RespondedRequest(
        new Request('GET', 'https://www.example.com/a/b'),
        new Response(200, [], $html),
    ));

    expect($links[0]->get())->toBe('https://www.example.com/c/e');
});

it('throws away the URL fragment part when withoutFragment() was called', function () {
    $html = <<<HTML
        <!DOCTYPE html>
        <html>
        <head></head>
        <body><a href="/foo/bar#fragment">link</a></body>
        </html>
        HTML;

    $step = (new GetLink());

    $respondedRequest = new RespondedRequest(
        new Request('GET', 'https://www.example.com/foo/baz'),
        new Response(200, [], $html),
    );

    $links = helper_invokeStepWithInput($step, $respondedRequest);

    expect($links[0]->get())->toBe('https://www.example.com/foo/bar#fragment');

    $step->withoutFragment();

    $links = helper_invokeStepWithInput($step, $respondedRequest);

    expect($links[0]->get())->toBe('https://www.example.com/foo/bar');
});

it('ignores special non HTTP links', function () {
    $html = <<<HTML
        <!DOCTYPE html>
        <html>
        <head></head>
        <body>
        <a href="mailto:somebody@example.com">mailto link</a>
        <a href="javascript:alert('hello');">javascript link</a>
        <a href="tel:+499123456789">phone link</a>
        <a href="/foo/bar">link</a>
        </body>
        </html>
        HTML;

    $step = (new GetLink());

    $respondedRequest = new RespondedRequest(
        new Request('GET', 'https://www.example.com/home'),
        new Response(200, [], $html),
    );

    $links = helper_invokeStepWithInput($step, $respondedRequest);

    expect($links[0]->get())->toBe('https://www.example.com/foo/bar');
});
