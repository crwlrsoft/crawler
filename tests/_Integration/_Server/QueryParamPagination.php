<?php

if (isset($_GET['page'])) {
    $query = 'page=' . $_GET['page'];
} else {
    $query = file_get_contents('php://input');
}

if (in_array($query, ['page=1', 'page=2', 'page=3'], true)) {
    echo '{ "data": { "items": ["one", "two", "three"] } }';
} else {
    echo '{ "data": { "items": [] } }';
}
