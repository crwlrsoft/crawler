<?php

namespace tests\_Integration\Http;

use Crwlr\Crawler\HttpCrawler;
use Crwlr\Crawler\Loader\Http\Browser\ScreenshotConfig;
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
use function tests\helper_resetStorageDir;
use function tests\helper_storagedir;

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

it(
    'does not use the user-agent defined in the crawler, when useNativeUserAgent() was called on the browser loader ' .
    'helper',
    function () {
        $crawler = new HeadlessBrowserCrawler();

        $crawler
            ->getLoader()
            ->browser()
            ->useNativeUserAgent();

        $crawler->input('http://localhost:8000/print-headers')
            ->addStep(Http::get())
            ->addStep((new GetJsonFromResponseHtmlBody())->keepAs('responseBody'));

        $results = helper_generatorToArray($crawler->run());

        expect($results)->toHaveCount(1)
            ->and($results[0]->get('responseBody'))->toBeArray()
            ->and($results[0]->get('responseBody'))->toHaveKey('User-Agent')
            ->and($results[0]->get('responseBody')['User-Agent'])->toStartWith('Mozilla/5.0 (');
    },
);

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

it('does not use cookies when HttpLoader::dontUseCookies() was called', function () {
    $crawler = new HeadlessBrowserCrawler();

    $crawler->getLoader()->dontUseCookies();

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
        ->and($results[0]->get('printed-cookie'))->toBeEmpty();
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

test(
    'BrowserAction::clickElement(), clickInsideShadowDom(), evaluate(), moveMouseToElement(), ' .
    'moveMouseToPosition(), scrollDown(), scrollUp() and typeText() work as expected',
    function () {
        $crawler = new HeadlessBrowserCrawler();

        $crawler
            ->getLoader()
            ->browser()
            ->includeShadowElementsInHtml();

        $crawler
            ->input('http://localhost:8000/browser-actions')
            ->addStep(
                Http::get()
                    // Inserting the #click_element is delayed in the page, so this also tests, that the
                    // BrowserAction::clickElement() action automatically waits for an element matching the selector
                    // to be present.
                    ->postBrowserNavigateHook(BrowserAction::clickElement('#click_element'))
                    ->postBrowserNavigateHook(BrowserAction::screenshot(ScreenshotConfig::make(helper_storagedir())))
                    ->postBrowserNavigateHook(BrowserAction::clickInsideShadowDom('#shadow_host', '#shadow_click_div'))
                    ->postBrowserNavigateHook(
                        BrowserAction::evaluate(
                            'document.getElementById(\'evaluation_container\').innerHTML = \'evaluated\'',
                        ),
                    )
                    ->postBrowserNavigateHook(BrowserAction::moveMouseToElement('#mouseover_check_1'))
                    ->postBrowserNavigateHook(BrowserAction::moveMouseToPosition(305, 405))
                    ->postBrowserNavigateHook(BrowserAction::scrollDown(4000))
                    ->postBrowserNavigateHook(
                        BrowserAction::screenshot(
                            ScreenshotConfig::make(helper_storagedir())
                                ->setImageFileType('jpeg')
                                ->setQuality(20)
                                ->setFullPage(),
                        ),
                    )
                    ->postBrowserNavigateHook(BrowserAction::scrollUp(2000))
                    ->postBrowserNavigateHook(BrowserAction::scrollUp(2000))
                    ->postBrowserNavigateHook(BrowserAction::clickElement('#input'))
                    ->postBrowserNavigateHook(BrowserAction::typeText('typing text works'))
                    ->keep(['body', 'screenshots']),
            );

        $results = helper_generatorToArray($crawler->run());

        $body = $results[0]->get('body');

        $screenshots = $results[0]->get('screenshots');

        expect($body)->toContain('<div id="click_worked">yes</div>')
            // This also tests the `HeadlessBrowserLoaderHelper::includeShadowElementsInHtml()` method,
            // because even if the click worked, with the normal way of getting HTML this wouldn't be
            // included in the returned HTML.
            ->and($body)->toContain('<div id="shadow_host"><div id="shadow_click_div">clicked</div></div>')
            ->and($body)->toContain('<div id="evaluation_container">evaluated</div>')
            ->and($body)->toContain('<div id="mouseover_check_1">mouse was here</div>')
            ->and($body)->toContain('<div id="mouseover_check_2">mouse was here</div>')
            ->and($body)->toContain('<div id="scroll_down_check">scrolled down</div>')
            ->and($body)->toContain('<div id="scroll_up_check">scrolled up</div>')
            ->and($body)->toContain('<div id="input_value">typing text works</div>')
            ->and($screenshots)->toHaveCount(2)
            ->and($screenshots[0])->toEndWith('.png')
            ->and($screenshots[1])->toEndWith('.jpeg');

        if (function_exists('getimagesize')) {
            $screenshot1Size = getimagesize($screenshots[0]);

            $screenshot2Size = getimagesize($screenshots[1]);

            if (is_array($screenshot1Size) && is_array($screenshot2Size)) {
                expect($screenshot1Size[1])->toBeLessThan(2100)
                    ->and($screenshot2Size[1])->toBeGreaterThan(4000);
            }
        }

        helper_resetStorageDir();
    },
);

test('BrowserAction::waitUntilDocumentContainsElement() works as expected', function () {
    $crawler = new HeadlessBrowserCrawler();

    $crawler
        ->input('http://localhost:8000/browser-actions/wait')
        ->addStep(
            Http::get()
                ->postBrowserNavigateHook(
                    BrowserAction::waitUntilDocumentContainsElement('#delayed_container'),
                )
                ->keep('body'),
        );

    $results = helper_generatorToArray($crawler->run());

    $body = $results[0]->get('body');

    expect($body)->toContain('<div id="delayed_container">hooray</div>');
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

test(
    'when on the click and wait for reload page, and the element is only clicked but we don\'t wait for reload, ' .
    'we don\'t get the reloaded page content',
    function () {
        $crawler = new HeadlessBrowserCrawler();

        $crawler
            ->input('http://localhost:8000/browser-actions/click-and-wait-for-reload')
            ->addStep(
                Http::get()
                    ->postBrowserNavigateHook(BrowserAction::clickElement('#click'))
                    ->keep('body'),
            );

        $results = helper_generatorToArray($crawler->run());

        $body = $results[0]->get('body');

        expect($body)->not()->toContain('<div id="reloaded">yes</div>');
    },
);

test(
    'when on the click and wait for reload page, and the element is clicked and we also wait for reload, we get the ' .
    'reloaded page content',
    function () {
        $crawler = new HeadlessBrowserCrawler();

        $crawler
            ->input('http://localhost:8000/browser-actions/click-and-wait-for-reload')
            ->addStep(
                Http::get()
                    ->postBrowserNavigateHook(BrowserAction::clickElement('#click'))
                    ->postBrowserNavigateHook(BrowserAction::waitForReload())
                    ->keep('body'),
            );

        $results = helper_generatorToArray($crawler->run());

        $body = $results[0]->get('body');

        expect($body)->toContain('<div id="reloaded">yes</div>');
    },
);

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

it('executes the javascript code provided via HeadlessBrowserLoaderHelper::setPageInitScript()', function () {
    $crawler = new HeadlessBrowserCrawler();

    $crawler
        ->getLoader()
        ->browser()
        ->setPageInitScript('window._secret_content = \'secret content\'');

    $crawler
        ->input('http://localhost:8000/page-init-script')
        ->addStep(Http::get())
        ->addStep(Html::root()->extract(['content' => '#content']));

    $results = helper_generatorToArray($crawler->run());

    expect($results[0]->get('content'))->toBe('secret content');
});

it('gets the source of an XML response without being wrapped in an HTML document', function () {
    $crawler = new HeadlessBrowserCrawler();

    $crawler
        ->input('http://localhost:8000/rss-feed')
        ->addStep(Http::get()->keep(['body']));

    $results = helper_generatorToArray($crawler->run());

    expect($results[0]->get('body'))->toStartWith('<?xml version="1.0" encoding="utf-8"?>' . PHP_EOL . '<rss');
});

it(
    'gets the source of an XML response without being wrapped in an HTML document even when chrome does not ' .
    'identify the document as an XML document',
    function () {
        $crawler = new HeadlessBrowserCrawler();

        $crawler
            ->input('http://localhost:8000/broken-mime-type-rss')
            ->addStep(Http::get()->keep(['body']));

        $results = helper_generatorToArray($crawler->run());

        expect($results[0]->get('body'))->toStartWith('<?xml version="1.0" encoding="UTF-8"?>');
    },
);
