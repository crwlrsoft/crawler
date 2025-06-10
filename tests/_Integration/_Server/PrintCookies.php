<?php

if (is_array($_COOKIE)) {
    $lastKey = array_key_last($_COOKIE);

    foreach ($_COOKIE as $key => $value) {
        echo $key . '=' . $value . ($key !== $lastKey ? ';' : '');
    }
}
