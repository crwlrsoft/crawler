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
