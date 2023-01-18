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

if ($route === '/print-cookie') {
    return include(__DIR__ . '/_Server/PrintCookie.php');
}

if (str_starts_with($route, '/crawling/')) {
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
    } elseif (str_starts_with($route, '/publisher/books/')) {
        $bookNo = (int) getParamAfter($route, '/publisher/books/');

        return include(__DIR__ . '/_Server/Publisher/BookDetailPage.php');
    }
}
