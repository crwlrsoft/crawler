<?php

namespace tests\Steps\Refiners\DateTime;

use Crwlr\Crawler\Steps\Refiners\DateTimeRefiner;
use tests\_Stubs\DummyLogger;

it('reformats common date/time strings without knowing the origin format', function (string $from, string $to) {
    $refinedValue = DateTimeRefiner::reformat('Y-m-d H:i:s')->refine($from);

    expect($refinedValue)->toBe($to);
})->with([
    ['2024-09-21T13:55:41Z', '2024-09-21 13:55:41'],
    ['2024-09-21T13:55:41.000Z', '2024-09-21 13:55:41'],
    ['2024-09-21', '2024-09-21 00:00:00'],
    ['2024-09-21, 13:55:41', '2024-09-21 13:55:41'],
    ['21 September 2024, 13:55:41', '2024-09-21 13:55:41'],
    ['21. September 2024, 13:55:41', '2024-09-21 13:55:41'],
    ['21 September 2024', '2024-09-21 00:00:00'],
    ['21. September 2024', '2024-09-21 00:00:00'],
    ['21.09.2024', '2024-09-21 00:00:00'],
    ['21.09.2024 13:55', '2024-09-21 13:55:00'],
    ['21.09.2024 13:55:41', '2024-09-21 13:55:41'],
    ['Sat, 21 September 2024 13:55:41 +0000', '2024-09-21 13:55:41'],
    ['Sat Sep 21 2024 16:55:41 GMT+0100', '2024-09-21 15:55:41'],
]);

it('reformats a format that PHP\'s strtotime() does not know, when the origin format is provided', function () {
    $refinedValue = DateTimeRefiner::reformat('Y-m-d H:i:s', 'd. F Y \u\m H:i:s')
        ->refine('21. September 2024 um 13:55:41');

    expect($refinedValue)->toBe('2024-09-21 13:55:41');
});

it('logs a warning message (and keeps original input) when it wasn\'t able to auto-convert a date time string', function () {
    $refiner = DateTimeRefiner::reformat('Y-m-d H:i:s');

    $logger = new DummyLogger();

    $refiner->addLogger($logger);

    $refinedValue = $refiner->refine('21. September 2024 um 13:55:41');

    expect($logger->messages)->toHaveCount(1)
        ->and($logger->messages[0]['level'])->toBe('warning')
        ->and($logger->messages[0]['message'])->toStartWith('Failed to automatically (without known format) parse')
        ->and($refinedValue)->toBe('21. September 2024 um 13:55:41');
});

it(
    'logs a warning message (and keeps original input) when it wasn\'t able to convert a date time string with the ' .
    'given origin format',
    function () {
        $refiner = DateTimeRefiner::reformat('Y-m-d H:i:s', 'd. F Y um H:i:s');

        $logger = new DummyLogger();

        $refiner->addLogger($logger);

        $refinedValue = $refiner->refine('21. September 2024 um 13:55:41');

        expect($logger->messages)->toHaveCount(1)
            ->and($logger->messages[0]['level'])->toBe('warning')
            ->and($logger->messages[0]['message'])->toStartWith('Failed parsing date/time ')
            ->and($refinedValue)->toBe('21. September 2024 um 13:55:41');
    },
);
