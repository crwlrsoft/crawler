<?php

namespace tests\_Integration\Http;

use Crwlr\Crawler\HttpCrawler;
use Crwlr\Crawler\Loader\Http\Cookies\Cookie;
use Crwlr\Crawler\Loader\Http\Cookies\CookieJar;
use Crwlr\Crawler\Loader\Http\HttpLoader;
use Crwlr\Crawler\Loader\LoaderInterface;
use Crwlr\Crawler\Steps\Dom\HtmlDocument;
use Crwlr\Crawler\Steps\Html;
use Crwlr\Crawler\Steps\Loading\Http;
use Crwlr\Crawler\Steps\Loading\Http\Browser\BrowserAction;
use Crwlr\Crawler\Steps\Step;
use Crwlr\Crawler\UserAgents\UserAgent;
use Crwlr\Crawler\UserAgents\UserAgentInterface;
use Generator;
use Psr\Log\LoggerInterface;

use function tests\helper_generatorToArray;
use function tests\helper_getFastLoader;

class HeadlessBrowserCrawler extends HttpCrawler
{
    protected function userAgent(): UserAgentInterface
    {
        return new UserAgent('HeadlessBrowserBot');
    }

    public function loader(UserAgentInterface $userAgent, LoggerInterface $logger): LoaderInterface
    {
        $loader = helper_getFastLoader($userAgent, $logger);

        $loader->useHeadlessBrowser();

        return $loader;
    }
}

class GetJsonFromResponseHtmlBody extends Step
{
    protected function invoke(mixed $input): Generator
    {
        $html = Http::getBodyString($input->response);

        $jsonString = (new HtmlDocument($html))->querySelector('body pre')?->text() ?? '';

        yield json_decode($jsonString, true);
    }
}

class GetStringFromResponseHtmlBody extends Step
{
    protected function invoke(mixed $input): Generator
    {
        $html = Http::getBodyString($input->response);

        yield (new HtmlDocument($html))->querySelector('body')?->text() ?? '';
    }
}

/**
 * @return Cookie[]
 */
function helper_getCookiesByDomainFromLoader(HttpLoader $loader, string $domain): array
{
    $cookieJar = invade($loader)->cookieJar;

    /** @var CookieJar $cookieJar */

    return $cookieJar->allByDomain($domain);
}

it('automatically uses the Loader\'s user agent', function () {
    $crawler = new HeadlessBrowserCrawler();

    $crawler->input('http://localhost:8000/print-headers')
        ->addStep(Http::get())
        ->addStep((new GetJsonFromResponseHtmlBody())->keepAs('responseBody'));

    $results = helper_generatorToArray($crawler->run());

    expect($results)->toHaveCount(1)
        ->and($results[0]->get('responseBody'))->toBeArray()
        ->and($results[0]->get('responseBody'))->toHaveKey('User-Agent')
        ->and($results[0]->get('responseBody')['User-Agent'])->toBe('HeadlessBrowserBot');
});

it('uses cookies', function () {
    $crawler = new HeadlessBrowserCrawler();

    $crawler
        ->input('http://localhost:8000/set-cookie')
        ->addStep(Http::get())
        ->addStep(new class extends Step {
            protected function invoke(mixed $input): Generator
            {
                yield 'http://localhost:8000/print-cookie';
            }
        })
        ->addStep(Http::get())
        ->addStep((new GetStringFromResponseHtmlBody())->keepAs('printed-cookie'));

    $results = helper_generatorToArray($crawler->run());

    expect($results)->toHaveCount(1)
        ->and($results[0]->get('printed-cookie'))->toBeString()
        ->and($results[0]->get('printed-cookie'))->toBe('foo123');
});

it('renders javascript', function () {
    $crawler = new HeadlessBrowserCrawler();

    $crawler->input('http://localhost:8000/js-rendering')
        ->addStep(Http::get())
        ->addStep(
            Html::root()
                ->extract(['content' => '#content p']),
        );

    $results = helper_generatorToArray($crawler->run());

    expect($results)->toHaveCount(1)
        ->and($results[0]->toArray())->toBe([
            'content' => 'This was added through javascript',
        ]);
});

it('gets cookies that are set via javascript', function () {
    $crawler = new HeadlessBrowserCrawler();

    $crawler
        ->input('http://localhost:8000/set-js-cookie')
        ->addStep(Http::get());

    helper_generatorToArray($crawler->run());

    $cookiesInJar = helper_getCookiesByDomainFromLoader($crawler->getLoader(), 'localhost');

    $testCookie = $cookiesInJar['testcookie'] ?? null;

    expect($cookiesInJar)->toHaveCount(1)
        ->and($testCookie?->name())->toBe('testcookie')
        ->and($testCookie?->value())->toBe('javascriptcookie');
});

it('gets a cookie that is set via a click, executed via post browser navigate hook', function () {
    $crawler = new HeadlessBrowserCrawler();

    $crawler
        ->input('http://localhost:8000/set-delayed-js-cookie')
        ->addStep(
            Http::get()
                ->postBrowserNavigateHook(BrowserAction::clickElement('#consent_btn')),
        )
        ->addStep(new class extends Step {
            protected function invoke(mixed $input): Generator
            {
                yield 'http://localhost:8000/print-cookie';
            }
        })
        ->addStep(Http::get())
        ->addStep((new GetStringFromResponseHtmlBody())->keepAs('printed-cookie'));

    $results = helper_generatorToArray($crawler->run());

    expect($results)->toHaveCount(1)
        ->and($results[0]->get('printed-cookie'))->toBeString()
        ->and($results[0]->get('printed-cookie'))->toBe('javascriptcookie');

    $cookiesInJar = helper_getCookiesByDomainFromLoader($crawler->getLoader(), 'localhost');

    $testCookie = $cookiesInJar['testcookie'] ?? null;

    expect($cookiesInJar)->toHaveCount(1)
        ->and($testCookie?->name())->toBe('testcookie')
        ->and($testCookie?->value())->toBe('javascriptcookie');
});

test('BrowserActions waitUntilDocumentContainsElement(), clickElement() and evaluate() work as expected', function () {
    $crawler = new HeadlessBrowserCrawler();

    $crawler
        ->input('http://localhost:8000/browser-actions')
        ->addStep(
            Http::get()
                ->postBrowserNavigateHook(
                    BrowserAction::waitUntilDocumentContainsElement('#delayed_el_container #delayed_el'),
                )
                ->postBrowserNavigateHook(BrowserAction::clickElement('#click_element'))
                ->postBrowserNavigateHook(
                    BrowserAction::evaluate(
                        'document.getElementById(\'evaluation_container\').innerHTML = \'evaluated\'',
                    ),
                )
                ->keep('body'),
        );

    $results = helper_generatorToArray($crawler->run());

    $body = $results[0]->get('body');

    expect($body)->toContain('<div id="delayed_el_container"><div id="delayed_el">a</div></div>')
        ->and($body)->toContain('<div id="click_worked">yes</div>')
        ->and($body)->toContain('<div id="evaluation_container">evaluated</div>');
});

test('BrowserAction::clickElementAndWaitForReload() works as expected', function () {
    $crawler = new HeadlessBrowserCrawler();

    $crawler
        ->input('http://localhost:8000/browser-actions/click-and-wait-for-reload')
        ->addStep(
            Http::get()
                ->postBrowserNavigateHook(BrowserAction::clickElementAndWaitForReload('#click'))
                ->keep('body'),
        );

    $results = helper_generatorToArray($crawler->run());

    $body = $results[0]->get('body');

    expect($body)->toContain('<div id="reloaded">yes</div>');
});

test('BrowserAction::evaluateAndWaitForReload() works as expected', function () {
    $crawler = new HeadlessBrowserCrawler();

    $crawler
        ->input('http://localhost:8000/browser-actions/evaluate-and-wait-for-reload')
        ->addStep(
            Http::get()
                ->postBrowserNavigateHook(
                    BrowserAction::evaluateAndWaitForReload(
                        'window.location.href = \'http://localhost:8000/browser-actions/' .
                            'evaluate-and-wait-for-reload-reloaded\'',
                    ),
                )
                ->keep('body'),
        );

    $results = helper_generatorToArray($crawler->run());

    $body = $results[0]->get('body');

    expect($body)->toContain('<div id="reloaded">yay</div>');
});

test('BrowserAction::wait() works as expected', function () {
    $crawler = new HeadlessBrowserCrawler();

    $crawler
        ->input('http://localhost:8000/browser-actions/wait')
        ->addStep(
            Http::get()
                ->postBrowserNavigateHook(BrowserAction::wait(0.3))
                ->keep('body'),
        );

    $results = helper_generatorToArray($crawler->run());

    $body = $results[0]->get('body');

    expect($body)->toContain('<div id="delayed_container">hooray</div>');
});
