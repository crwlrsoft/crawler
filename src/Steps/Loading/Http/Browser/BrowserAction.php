<?php

namespace Crwlr\Crawler\Steps\Loading\Http\Browser;

use Closure;
use Crwlr\Crawler\Loader\Http\Browser\Screenshot;
use Crwlr\Crawler\Loader\Http\Browser\ScreenshotConfig;
use Crwlr\Utils\Microseconds;
use HeadlessChromium\Page;
use Psr\Log\LoggerInterface;
use Throwable;

class BrowserAction
{
    public const DEFAULT_TIMEOUT = 15_000;

    public static function waitUntilDocumentContainsElement(
        string $cssSelector,
        int $timeout = self::DEFAULT_TIMEOUT,
    ): Closure {
        return function (Page $page) use ($cssSelector, $timeout) {
            $page->waitUntilContainsElement($cssSelector, $timeout);
        };
    }

    public static function clickElement(
        string $cssSelector,
        int $timeout = self::DEFAULT_TIMEOUT,
    ): Closure {
        return function (Page $page) use ($cssSelector, $timeout) {
            $page->waitUntilContainsElement($cssSelector, $timeout);

            $page->mouse()->find($cssSelector)->click();
        };
    }

    /**
     * Click an element that lives inside a shadow DOM within the document.
     *
     * For this purpose the action needs two selectors: the first one to select the shadow host element and the
     * second one to select the element that shall be clicked inside that shadow DOM.
     */
    public static function clickInsideShadowDom(
        string $shadowHostSelector,
        string $clickElementSelector,
        int $timeout = self::DEFAULT_TIMEOUT,
    ): Closure {
        return function (Page $page) use ($shadowHostSelector, $clickElementSelector, $timeout) {
            $page->evaluate(<<<JS
            (async function() {
                let shadowHostElement = document.querySelector('{$shadowHostSelector}');

                while (!shadowHostElement) {
                    await new Promise(resolve => setTimeout(resolve, 25));
                    shadowHostElement = document.querySelector('{$shadowHostSelector}');
                }

                if (shadowHostElement.shadowRoot) {
                    let clickElement = shadowHostElement.shadowRoot.querySelector('{$clickElementSelector}');

                    while (!clickElement) {
                        await new Promise(resolve => setTimeout(resolve, 25));
                        clickElement = shadowHostElement.shadowRoot.querySelector('{$clickElementSelector}');
                    }

                    clickElement.dispatchEvent(new MouseEvent("click", { bubbles: true }));
                }
            })()
            JS)->waitForResponse($timeout);
        };
    }

    public static function moveMouseToElement(string $cssSelector, int $timeout = self::DEFAULT_TIMEOUT): Closure
    {
        return function (Page $page) use ($cssSelector, $timeout) {
            $page->waitUntilContainsElement($cssSelector, $timeout);

            $page->mouse()->find($cssSelector);
        };
    }

    public static function moveMouseToPosition(int $x, int $y, ?int $steps = null): Closure
    {
        return function (Page $page) use ($x, $y, $steps) {
            if ($steps !== null) {
                $page->mouse()->move($x, $y, ['steps' => $steps]);
            } else {
                $page->mouse()->move($x, $y);
            }
        };
    }

    public static function scrollDown(int $distance): Closure
    {
        return function (Page $page) use ($distance) {
            $page->mouse()->scrollDown($distance);
        };
    }

    public static function scrollUp(int $distance): Closure
    {
        return function (Page $page) use ($distance) {
            $page->mouse()->scrollUp($distance);
        };
    }

    public static function typeText(string $text, ?int $delay = null): Closure
    {
        return function (Page $page) use ($text, $delay) {
            if ($delay !== null) {
                $page->keyboard()->setKeyInterval($delay)->typeText($text);
            } else {
                $page->keyboard()->typeText($text);
            }
        };
    }

    public static function evaluate(string $jsCode): Closure
    {
        return function (Page $page) use ($jsCode) {
            $page->evaluate($jsCode);
        };
    }

    public static function waitForReload(int $timeout = self::DEFAULT_TIMEOUT): Closure
    {
        return function (Page $page) use ($timeout) {
            $page->waitForReload(timeout: $timeout);
        };
    }

    public static function wait(float $seconds): Closure
    {
        return function () use ($seconds) {
            usleep(Microseconds::fromSeconds($seconds)->value);
        };
    }

    public static function screenshot(ScreenshotConfig $config): Closure
    {
        return function (Page $page, ?LoggerInterface $logger) use ($config) {
            $fullFilePath = $config->getFullPath($page);

            try {
                $page->screenshot($config->toChromePhpScreenshotConfig($page))->saveToFile($fullFilePath);

                return new Screenshot($fullFilePath);
            } catch (Throwable $exception) {
                $logger?->error('Failed to take screenshot.');

                $logger?->debug($exception->getMessage());

                return null;
            }
        };
    }

    /**
     * @deprecated Use the two methods evaluate() and waitForReload() separately.
     */
    public static function evaluateAndWaitForReload(string $jsCode): Closure
    {
        return function (Page $page) use ($jsCode) {
            $page->evaluate($jsCode)->waitForPageReload();
        };
    }

    /**
     * @deprecated Use the two methods clickElement() and waitForReload() separately.
     */
    public static function clickElementAndWaitForReload(string $cssSelector): Closure
    {
        return function (Page $page) use ($cssSelector) {
            $page->waitUntilContainsElement($cssSelector);

            $page->mouse()->find($cssSelector)->click();

            $page->waitForReload();
        };
    }
}
