<?php

namespace tests\Logger;

use Crwlr\Crawler\Logger\PreStepInvocationLogger;
use tests\_Stubs\DummyLogger;

it('logs messages', function () {
    $logger = new PreStepInvocationLogger();

    $logger->info('test');

    $logger->warning('foo');

    $logger->error('some error');

    expect($logger->messages)->toHaveCount(3)
        ->and($logger->messages[0]['level'])->toBe('info')
        ->and($logger->messages[0]['message'])->toBe('test')
        ->and($logger->messages[1]['level'])->toBe('warning')
        ->and($logger->messages[1]['message'])->toBe('foo')
        ->and($logger->messages[2]['level'])->toBe('error')
        ->and($logger->messages[2]['message'])->toBe('some error');
});

it('passes log messages to another logger', function () {
    $logger = new PreStepInvocationLogger();

    $logger->info('test');

    $logger->warning('foo');

    $logger->error('some error');

    $anotherLogger = new DummyLogger();

    $logger->passToOtherLogger($anotherLogger);

    expect($anotherLogger->messages)->toHaveCount(3)
        ->and($anotherLogger->messages[0]['level'])->toBe('info')
        ->and($anotherLogger->messages[0]['message'])->toBe('test')
        ->and($anotherLogger->messages[1]['level'])->toBe('warning')
        ->and($anotherLogger->messages[1]['message'])->toBe('foo')
        ->and($anotherLogger->messages[2]['level'])->toBe('error')
        ->and($anotherLogger->messages[2]['message'])->toBe('some error');
});
