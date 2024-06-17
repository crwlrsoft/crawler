<?php

namespace tests\Loader\Http;

use Closure;
use Crwlr\Crawler\Loader\Http\HeadlessBrowserLoaderHelper;
use Crwlr\Crawler\Steps\Loading\Http;
use Exception;
use GuzzleHttp\Psr7\Request;
use HeadlessChromium\AutoDiscover;
use HeadlessChromium\Browser\ProcessAwareBrowser;
use HeadlessChromium\BrowserFactory;
use HeadlessChromium\Communication\Session;
use HeadlessChromium\Page;
use HeadlessChromium\PageUtils\PageNavigation;
use Mockery;

use function tests\helper_getMinThrottler;

function helper_setUpHeadlessChromeMocks(
    ?Closure $pageNavigationArgsClosure = null,
): BrowserFactory {
    $browserFactoryMock = Mockery::mock(BrowserFactory::class);

    $browserMock = Mockery::mock(ProcessAwareBrowser::class);

    $browserFactoryMock->shouldReceive('createBrowser')->andReturn($browserMock);

    $pageMock = Mockery::mock(Page::class);

    $browserMock->shouldReceive('createPage')->andReturn($pageMock);

    $sessionMock = Mockery::mock(Session::class);

    $pageMock->shouldReceive('getSession')->andReturn($sessionMock);

    $sessionMock->shouldReceive('once');

    $pageNavigationMock = Mockery::mock(PageNavigation::class);

    $pageMock->shouldReceive('navigate')->andReturn($pageNavigationMock);

    $pageMock->shouldReceive('getHtml')->andReturn('<html><head></head><body>Hello World!</body></html>');

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
