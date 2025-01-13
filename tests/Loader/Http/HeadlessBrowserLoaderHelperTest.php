<?php

namespace tests\Loader\Http;

use Closure;
use Crwlr\Crawler\Loader\Http\Cookies\CookieJar;
use Crwlr\Crawler\Loader\Http\HeadlessBrowserLoaderHelper;
use Crwlr\Crawler\Steps\Loading\Http;
use Exception;
use GuzzleHttp\Psr7\Request;
use HeadlessChromium\AutoDiscover;
use HeadlessChromium\Browser\ProcessAwareBrowser;
use HeadlessChromium\BrowserFactory;
use HeadlessChromium\Communication\Message;
use HeadlessChromium\Communication\Session;
use HeadlessChromium\Cookies\CookiesCollection;
use HeadlessChromium\Page;
use HeadlessChromium\PageUtils\PageNavigation;
use Mockery;

use function tests\helper_getMinThrottler;

function helper_setUpHeadlessChromeMocks(
    ?Closure $pageNavigationArgsClosure = null,
    ?Closure $createBrowserArgsExpectationCallback = null,
    ?Closure $browserMockCallback = null,
    ?Closure $pageSessionMockCallback = null,
    ?Closure $pageMockCallback = null,
): BrowserFactory {
    $browserFactoryMock = Mockery::mock(BrowserFactory::class);

    $browserMock = Mockery::mock(ProcessAwareBrowser::class);

    $createBrowserExpectation = $browserFactoryMock->shouldReceive('createBrowser');

    if ($createBrowserArgsExpectationCallback) {
        $createBrowserExpectation->withArgs($createBrowserArgsExpectationCallback);
    }

    $createBrowserExpectation->andReturn($browserMock);

    $pageMock = Mockery::mock(Page::class);

    $browserMock->shouldReceive('createPage')->andReturn($pageMock);

    if ($browserMockCallback) {
        $browserMockCallback($browserMock);
    }

    $sessionMock = Mockery::mock(Session::class);

    $pageMock->shouldReceive('getSession')->andReturn($sessionMock);

    if ($pageSessionMockCallback) {
        $pageSessionMockCallback($sessionMock);
    }

    $pageMock->shouldReceive('getCookies')->andReturn(new CookiesCollection([]));

    $sessionMock->shouldReceive('once');

    $pageNavigationMock = Mockery::mock(PageNavigation::class);

    $pageMock->shouldReceive('navigate')->andReturn($pageNavigationMock);

    $pageMock->shouldReceive('getHtml')->andReturn('<html><head></head><body>Hello World!</body></html>');

    if ($pageMockCallback) {
        $pageMockCallback($pageMock);
    }

    $waitForNavigationCall = $pageNavigationMock->shouldReceive('waitForNavigation');

    if ($pageNavigationArgsClosure) {
        $waitForNavigationCall->withArgs($pageNavigationArgsClosure);
    }

    return $browserFactoryMock;
}

it('uses the configured timeout', function () {
    $browserFactoryMock = helper_setUpHeadlessChromeMocks(function (string $event, int $timeout) {
        return $event === Page::LOAD && $timeout === 45_000;
    });

    $helper = new HeadlessBrowserLoaderHelper($browserFactoryMock);

    $helper->setTimeout(45_000);

    $response = $helper->navigateToPageAndGetRespondedRequest(
        new Request('GET', 'https://www.example.com/foo'),
        helper_getMinThrottler(),
        cookieJar: new CookieJar(),
    );

    expect(Http::getBodyString($response))->toBe('<html><head></head><body>Hello World!</body></html>');
});

it('returns the configured timeout', function () {
    $helper = new HeadlessBrowserLoaderHelper();

    expect($helper->getTimeout())->toBe(30_000);

    $helper->setTimeout(75_000);

    expect($helper->getTimeout())->toBe(75_000);
});

it('waits for the configured browser navigation event', function () {
    $browserFactoryMock = helper_setUpHeadlessChromeMocks(function (string $event, int $timeout) {
        return $event === Page::FIRST_MEANINGFUL_PAINT && $timeout === 57_000;
    });

    $helper = new HeadlessBrowserLoaderHelper($browserFactoryMock);

    $helper
        ->waitForNavigationEvent(Page::FIRST_MEANINGFUL_PAINT)
        ->setTimeout(57_000);

    $response = $helper->navigateToPageAndGetRespondedRequest(
        new Request('GET', 'https://www.example.com/foo'),
        helper_getMinThrottler(),
        cookieJar: new CookieJar(),
    );

    expect(Http::getBodyString($response))->toBe('<html><head></head><body>Hello World!</body></html>');
});

it('uses the correct executable', function () {
    $helper = new HeadlessBrowserLoaderHelper();

    $helper->setExecutable('somethingthatdefinitelyisntachromeexecutable');

    $invadedHelper = invade($helper);

    $exception = null;

    try {
        $invadedHelper->getBrowser(new Request('GET', 'https://www.example.com/foo'));
    } catch (Exception $exception) {
    }

    expect($exception)->not->toBeNull();

    $chromeExecutable = (new AutoDiscover())->guessChromeBinaryPath();

    $helper = new HeadlessBrowserLoaderHelper();

    $helper->setExecutable($chromeExecutable);

    $invadedHelper = invade($helper);

    $invadedHelper->getBrowser(new Request('GET', 'https://www.example.com/foo'));

    $browserFactory = $invadedHelper->browserFactory;

    expect($browserFactory)->toBeInstanceOf(BrowserFactory::class);

    /** @var BrowserFactory $browserFactory */

    $invadedBrowserFactory = invade($browserFactory);

    expect($invadedBrowserFactory->chromeBinary)->toBe($chromeExecutable);
});

it('calls the temporary post navigate hooks once', function () {
    $browserFactoryMock = helper_setUpHeadlessChromeMocks(
        pageMockCallback: function (Mockery\MockInterface $pageMock) {
            $pageMock->shouldReceive('assertNotClosed')->once();
        },
    );

    $helper = new HeadlessBrowserLoaderHelper($browserFactoryMock);

    $hook1Called = $hook2Called = $hook3Called = false;

    $helper->setTempPostNavigateHooks([
        function (Page $page) use (& $hook1Called) {
            $hook1Called = true;
        },
        function (Page $page) use (& $hook2Called) {
            $hook2Called = true;
        },
        function (Page $page) use (& $hook3Called) {
            $hook3Called = true;
        },
    ]);

    $helper->navigateToPageAndGetRespondedRequest(
        new Request('GET', 'https://www.example.com/foo'),
        helper_getMinThrottler(),
        cookieJar: new CookieJar(),
    );

    expect($hook1Called)->toBeTrue()
        ->and($hook2Called)->toBeTrue()
        ->and($hook3Called)->toBeTrue();

    $hook1Called = $hook2Called = $hook3Called = false;

    $helper->navigateToPageAndGetRespondedRequest(
        new Request('GET', 'https://www.example.com/foo'),
        helper_getMinThrottler(),
        cookieJar: new CookieJar(),
    );

    expect($hook1Called)->toBeFalse()
        ->and($hook2Called)->toBeFalse()
        ->and($hook3Called)->toBeFalse();
});

it(
    'passes the script source provided via the setPageInitScript() method, to the ' .
    'ProcessAwareBrowser::setPagePreScript() method',
    function () {
        $script = 'console.log(\'hey\');';

        $browserFactoryMock = helper_setUpHeadlessChromeMocks(
            browserMockCallback: function (Mockery\MockInterface $browser) use ($script) {
                $browser
                    ->shouldReceive('setPagePreScript')
                    ->once()
                    ->with($script);
            },
        );

        $helper = new HeadlessBrowserLoaderHelper($browserFactoryMock);

        $helper->setPageInitScript($script);

        $helper->navigateToPageAndGetRespondedRequest(
            new Request('GET', 'https://www.example.com/bar'),
            helper_getMinThrottler(),
            cookieJar: new CookieJar(),
        );
    },
);

it('does not call the ProcessAwareBrowser::setPagePreScript() when no page init script was defined', function () {
    $browserFactoryMock = helper_setUpHeadlessChromeMocks(
        browserMockCallback: function (Mockery\MockInterface $browser) {
            $browser->shouldNotReceive('setPagePreScript');
        },
    );

    $helper = new HeadlessBrowserLoaderHelper($browserFactoryMock);

    $helper->navigateToPageAndGetRespondedRequest(
        new Request('GET', 'https://www.example.com/bar'),
        helper_getMinThrottler(),
        cookieJar: new CookieJar(),
    );
});

it(
    'passes the userAgent option when Request contains a user-agent header and useNativeUserAgent() was not called',
    function () {
        $browserFactoryMock = helper_setUpHeadlessChromeMocks(
            createBrowserArgsExpectationCallback: function ($options) {
                return array_key_exists('userAgent', $options) && $options['userAgent'] === 'MyBot';
            },
        );

        $helper = new HeadlessBrowserLoaderHelper($browserFactoryMock);

        $response = $helper->navigateToPageAndGetRespondedRequest(
            new Request('GET', 'https://www.example.com/bar', ['user-agent' => ['MyBot']]),
            helper_getMinThrottler(),
            cookieJar: new CookieJar(),
        );

        expect(Http::getBodyString($response))->toBe('<html><head></head><body>Hello World!</body></html>');
    },
);

it(
    'does not pass the userAgent option when Request contains a user-agent header and useNativeUserAgent() was called',
    function () {
        $browserFactoryMock = helper_setUpHeadlessChromeMocks(
            createBrowserArgsExpectationCallback: function ($options) {
                return !array_key_exists('userAgent', $options);
            },
        );

        $helper = new HeadlessBrowserLoaderHelper($browserFactoryMock);

        $helper->useNativeUserAgent();

        $response = $helper->navigateToPageAndGetRespondedRequest(
            new Request('GET', 'https://www.example.com/bar', ['user-agent' => ['MyBot']]),
            helper_getMinThrottler(),
            cookieJar: new CookieJar(),
        );

        expect(Http::getBodyString($response))->toBe('<html><head></head><body>Hello World!</body></html>');
    },
);

it('clears the browsers cookies when no cookie jar is provided', function () {
    $browserFactoryMock = helper_setUpHeadlessChromeMocks(
        pageSessionMockCallback: function (Mockery\MockInterface $mock) {
            $mock
                ->shouldReceive('sendMessageSync')
                ->once()
                ->withArgs(function (Message $message) {
                    return $message->getMethod() === 'Network.clearBrowserCookies';
                });
        },
    );

    $helper = new HeadlessBrowserLoaderHelper($browserFactoryMock);

    $response = $helper->navigateToPageAndGetRespondedRequest(
        new Request('GET', 'https://www.example.com/yolo', ['user-agent' => ['MyBot']]),
        helper_getMinThrottler(),
    );

    expect(Http::getBodyString($response))->toBe('<html><head></head><body>Hello World!</body></html>');
});

it('reuses a previously opened page', function () {
    $browserFactoryMock = helper_setUpHeadlessChromeMocks(
        pageMockCallback: function (Mockery\MockInterface $pageMock) {
            $pageMock->shouldReceive('assertNotClosed')->twice();
        },
    );

    $helper = new HeadlessBrowserLoaderHelper($browserFactoryMock);

    $t = helper_getMinThrottler();

    $c = new CookieJar();

    $helper->navigateToPageAndGetRespondedRequest(new Request('GET', 'https://www.example.com/foo'), $t, null, $c);

    $helper->navigateToPageAndGetRespondedRequest(new Request('GET', 'https://www.example.com/bar'), $t, null, $c);

    $helper->navigateToPageAndGetRespondedRequest(new Request('GET', 'https://www.example.com/baz'), $t, null, $c);
});
