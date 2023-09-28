<?php

echo "Proxy Server Response for " . ($_SERVER['REQUEST_URI'] ?? '?') . PHP_EOL . PHP_EOL;

echo "Port: " . $_SERVER['SERVER_PORT'] . PHP_EOL;

echo "Protocol Version: " . $_SERVER['SERVER_PROTOCOL'] . PHP_EOL;

echo "Request Method: " . $_SERVER['REQUEST_METHOD'] . PHP_EOL;

echo "Request Body: " . file_get_contents('php://input') . PHP_EOL;

var_dump(getallheaders());
