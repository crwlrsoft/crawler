<?php

$route = $_SERVER['REQUEST_URI'];

function getParamAfter(string $route, string $after): string
{
    if ($after === '') {
        return $route;
    }

    $result = explode($after, $route);

    return explode('/', $result[1])[0];
}

if ($route === '/simple-listing') {
    return include(__DIR__ . '/_Server/SimpleListing.php');
}

if (str_starts_with($route, '/simple-listing/article/')) {
    $articleId = getParamAfter($route, '/simple-listing/article/');

    return include(__DIR__ . '/_Server/SimpleListing/Detail.php');
}

if (str_starts_with($route, '/paginated-listing')) {
    if (str_starts_with($route, '/paginated-listing/items/')) {
        $itemId = getParamAfter($route, '/paginated-listing/items/');

        return include(__DIR__ . '/_Server/PaginatedListing/Detail.php');
    }

    return include(__DIR__ . '/_Server/PaginatedListing.php');
}

if (str_starts_with($route, '/query-param-pagination')) {
    return include(__DIR__ . '/_Server/QueryParamPagination.php');
}

if ($route === '/blog-post-with-json-ld') {
    return include(__DIR__ . '/_Server/BlogPostWithJsonLd.php');
}

if ($route === '/js-rendering') {
    return include(__DIR__ . '/_Server/JsGeneratedContent.php');
}

if ($route === '/print-headers') {
    return include(__DIR__ . '/_Server/PrintHeaders.php');
}

if ($route === '/set-cookie') {
    return include(__DIR__ . '/_Server/SetCookie.php');
}

if ($route === '/set-js-cookie') {
    return include(__DIR__ . '/_Server/SetCookieJs.php');
}

if ($route === '/scripts/set-cookie.js') {
    echo <<<JS
        document.addEventListener("DOMContentLoaded", function () {
            document.getElementById('consent_btn').addEventListener('click', function (ev) {
                ev.preventDefault();
                document.cookie = "testcookie=javascriptcookie";
            }, false);
        }, false);
        JS;
    return;
}

if ($route === '/set-delayed-js-cookie') {
    return include(__DIR__ . '/_Server/SetDelayedCookieJs.php');
}

if ($route === '/set-multiple-js-cookies') {
    return include(__DIR__ . '/_Server/SetMultipleCookiesJs.php');
}

if (str_starts_with($route, '/browser-actions')) {
    if ($route === '/browser-actions') {
        return include(__DIR__ . '/_Server/BrowserActions/Main.php');
    }

    if (str_starts_with($route, '/browser-actions/click-and-wait-for-reload')) {
        return include(__DIR__ . '/_Server/BrowserActions/ClickAndWaitForReload.php');
    }

    if ($route === '/browser-actions/evaluate-and-wait-for-reload') {
        return include(__DIR__ . '/_Server/BrowserActions/EvaluateAndWaitForReload.php');
    }

    if ($route === '/browser-actions/evaluate-and-wait-for-reload-reloaded') {
        return include(__DIR__ . '/_Server/BrowserActions/EvaluateAndWaitForReloadReloaded.php');
    }

    if ($route === '/browser-actions/wait') {
        return include(__DIR__ . '/_Server/BrowserActions/Wait.php');
    }
}

if ($route === '/print-cookie') {
    return include(__DIR__ . '/_Server/PrintCookie.php');
}

if ($route === '/print-cookies') {
    return include(__DIR__ . '/_Server/PrintCookies.php');
}

if (str_starts_with($route, '/crawling')) {
    return include(__DIR__ . '/_Server/Crawling.php');
}

if (str_starts_with($route, '/too-many-requests')) {
    if (str_ends_with($route, '/succeed-on-second-attempt')) {
        session_start();

        $isSecondRequest = isset($_SESSION["isSecondRequest"]) && $_SESSION["isSecondRequest"] === true;

        if (!$isSecondRequest) {
            $_SESSION["isSecondRequest"] = true;
        }
    }

    $retryAfter = str_ends_with($route, '/retry-after') ? 2 : null;

    return include(__DIR__ . '/_Server/TooManyRequests.php');
}

if (str_starts_with($route, '/service-unavailable')) {
    if (str_ends_with($route, '/succeed-on-second-attempt')) {
        session_start();

        $isSecondRequest = isset($_SESSION["isSecondRequest"]) && $_SESSION["isSecondRequest"] === true;

        if (!$isSecondRequest) {
            $_SESSION["isSecondRequest"] = true;
        }
    }

    $retryAfter = str_ends_with($route, '/retry-after') ? 2 : null;

    return include(__DIR__ . '/_Server/TooManyRequests.php');
}

if (str_starts_with($route, '/client-error-response')) {
    $responseCodes = [400, 401, 404, 405, 410];

    http_response_code($responseCodes[rand(0, 4)]);

    return;
}

if (str_starts_with($route, '/server-error-response')) {
    $responseCodes = [500, 502, 505, 521];

    http_response_code($responseCodes[rand(0, 3)]);

    return;
}

if (str_starts_with($route, '/gzip')) {
    header('Content-Type: application/x-gzip');

    echo gzencode('This is a gzip compressed string');
}

if (str_starts_with($route, '/sleep')) {
    usleep(1050000);

    return;
}

if (str_starts_with($route, '/publisher')) {
    if ($route === '/publisher/authors') {
        return include(__DIR__ . '/_Server/Publisher/AuthorsListPage.php');
    } elseif (str_starts_with($route, '/publisher/authors/')) {
        $author = getParamAfter($route, '/publisher/authors/');

        return include(__DIR__ . '/_Server/Publisher/AuthorDetailPage.php');
    } elseif (str_starts_with($route, '/publisher/books/') && str_contains($route, '/edition/')) {
        $bookNo = (int) getParamAfter($route, '/publisher/books/');

        $edition = (int) getParamAfter($route, '/edition/');

        return include(__DIR__ . '/_Server/Publisher/EditionDetailPage.php');
    } elseif (str_starts_with($route, '/publisher/books/')) {
        $bookNo = (int) getParamAfter($route, '/publisher/books/');

        return include(__DIR__ . '/_Server/Publisher/BookDetailPage.php');
    }
}

if (str_starts_with($route, '/redirect')) {
    $redirectNo = (int) ($_GET['no'] ?? 0);

    $stopAt = $_GET['stopAt'] ?? null;

    if ($stopAt && is_numeric($stopAt)) {
        $stopAt = (int) $stopAt;

        if ($redirectNo >= $stopAt) {
            echo 'success after ' . $redirectNo . ' redirects';

            return;
        } else {
            $stopAt = '&stopAt=' . $stopAt;
        }
    } else {
        $stopAt = '';
    }

    header('Location: http://localhost:8000/redirect?no=' . ($redirectNo + 1) . $stopAt);
}

if (str_starts_with($route, '/non-utf-8-charset')) {
    return include(__DIR__ . '/_Server/NonUtf8.php');
}

if (str_starts_with($route, '/page-init-script')) {
    return include(__DIR__ . '/_Server/PageInitScript.php');
}

if ($route === '/rss-feed') {
    header('Content-Type: text/xml; charset=utf-8');

    return include(__DIR__ . '/_Server/RssFeed.php');
}

if ($route === '/broken-mime-type-rss') {
    header('Content-Type: application/rss+xml; charset=UTF-8');

    return include(__DIR__ . '/_Server/BrokenMimeTypeRss.php');
}

if ($route === '/robots.txt') {
    return <<<ROBOTSTXT
        User-Agent: *
        Disallow:
        ROBOTSTXT;
}

if ($route === '/hello-world') {
    return include(__DIR__ . '/_Server/HelloWorld.php');
}
