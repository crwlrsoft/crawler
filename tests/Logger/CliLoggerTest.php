<?php

namespace tests\Logger;

use Crwlr\Crawler\Logger\CliLogger;
use PHPUnit\Framework\TestCase;

/** @var TestCase $this */

test('It prints a message', function () {
    $logger = new CliLogger();
    $logger->log('info', 'Some log message.');
    $output = $this->getActualOutput();
    expect($output)->toContain('Some log message.');
});

test('It prints the log level', function () {
    $logger = new CliLogger();
    $logger->log('alert', 'Everybody panic!');
    $output = $this->getActualOutput();
    expect($output)->toContain('[ALERT]');
});

test('It starts with printing the time', function () {
    $logger = new CliLogger();
    $logger->log('warning', 'Warn about something.');
    $this->expectOutputRegex('/^\d\d:\d\d:\d\d:\d\d\d\d\d\d/');
});

test('It has methods for all the log levels', function ($logLevel) {
    $logger = new CliLogger();
    $logger->{$logLevel}('Some message');
    $output = $this->getActualOutput();
    expect($output)->toContain('Some message');
    expect($output)->toContain('[' . strtoupper($logLevel) . ']');
})->with([
    'emergency',
    'alert',
    'critical',
    'error',
    'warning',
    'notice',
    'info',
    'debug',
]);
