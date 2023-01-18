<?php

if (!isset($isSecondRequest) || $isSecondRequest !== true) {
    http_response_code(503);
}

if (isset($retryAfter)) {
    header('Retry-After: ' . $retryAfter);
}
