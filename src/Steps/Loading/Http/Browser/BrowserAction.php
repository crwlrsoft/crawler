<?php

namespace Crwlr\Crawler\Steps\Loading\Http\Browser;

use Closure;
use Crwlr\Utils\Microseconds;
use HeadlessChromium\Page;

class BrowserAction
{
    public static function waitUntilDocumentContainsElement(string $cssSelector): Closure
    {
        return function (Page $page) use ($cssSelector) {
            $page->waitUntilContainsElement($cssSelector);
        };
    }

    public static function clickElement(string $cssSelector): Closure
    {
        return function (Page $page) use ($cssSelector) {
            $page->mouse()->find($cssSelector)->click();
        };
    }

    public static function clickElementAndWaitForReload(string $cssSelector): Closure
    {
        return function (Page $page) use ($cssSelector) {
            $page->mouse()->find($cssSelector)->click();

            $page->waitForReload();
        };
    }

    public static function evaluate(string $jsCode): Closure
    {
        return function (Page $page) use ($jsCode) {
            $page->evaluate($jsCode);
        };
    }

    public static function evaluateAndWaitForReload(string $jsCode): Closure
    {
        return function (Page $page) use ($jsCode) {
            $page->evaluate($jsCode)->waitForPageReload();
        };
    }

    public static function wait(float $seconds): Closure
    {
        return function (Page $page) use ($seconds) {
            usleep(Microseconds::fromSeconds($seconds)->value);
        };
    }
}
