<?php

$route = $_SERVER['REQUEST_URI'];

if ($route === '/simple-listing') {
    return include(__DIR__ . '/_Server/SimpleListing.php');
}

if (str_starts_with($route, '/simple-listing/article/')) {
    $articleId = explode('/simple-listing/article/', $route);

    $articleId = explode('/', $articleId[1])[0];

    return include(__DIR__ . '/_Server/SimpleListing/Detail.php');
}
