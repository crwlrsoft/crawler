<?php

namespace tests\Utils;

use Crwlr\Crawler\Utils\Gzip;

it('encodes a string', function () {
    $string = str_repeat('Hello World! ', 100);

    $compressed = Gzip::encode($string);

    expect($compressed)->not->toBe($string)
        ->and(strlen($compressed))->toBeLessThan(strlen($string));
});

it('decodes a string', function () {
    $encoded = Gzip::encode('Hello World!');

    expect($encoded)->not->toBe('Hello World!')
        ->and(Gzip::decode($encoded))->toBe('Hello World!');
});

it('does not generate a warning, when string to decode actually isn\'t encoded', function () {
    $warnings = [];

    set_error_handler(function ($errno, $errstr) use (&$warnings) {
        if ($errno === E_WARNING) {
            $warnings[] = $errstr;
        }

        return false;
    });

    $decoded = Gzip::decode('Hello World!');

    restore_error_handler();

    expect($decoded)->toBe('Hello World!')
        ->and($warnings)->toBeEmpty();
});
