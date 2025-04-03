<?php

namespace Crwlr\Crawler\Logger;

use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Stringable;
use UnexpectedValueException;

class PreStepInvocationLogger implements LoggerInterface
{
    /**
     * @var array<int, array<string, string>>
     */
    public array $messages = [];

    public function emergency(string|Stringable $message, array $context = []): void
    {
        $this->log('emergency', $message, $context);
    }

    public function alert(string|Stringable $message, array $context = []): void
    {
        $this->log('alert', $message, $context);
    }

    public function critical(string|Stringable $message, array $context = []): void
    {
        $this->log('critical', $message, $context);
    }

    public function error(string|Stringable $message, array $context = []): void
    {
        $this->log('error', $message, $context);
    }

    public function warning(string|Stringable $message, array $context = []): void
    {
        $this->log('warning', $message, $context);
    }

    public function notice(string|Stringable $message, array $context = []): void
    {
        $this->log('notice', $message, $context);
    }

    public function info(string|Stringable $message, array $context = []): void
    {
        $this->log('info', $message, $context);
    }

    public function debug(string|Stringable $message, array $context = []): void
    {
        $this->log('debug', $message, $context);
    }

    /**
     * @param string $level
     * @param mixed[] $context
     */
    public function log($level, string|Stringable $message, array $context = []): void
    {
        if (!is_string($level)) {
            throw new InvalidArgumentException('Level must be string.');
        }

        if (!in_array($level, ['emergency', 'alert', 'critical', 'error', 'warning', 'notice', 'info', 'debug'], true)) {
            throw new UnexpectedValueException('Unknown log level.');
        }

        $this->messages[] = ['level' => $level, 'message' => $message];
    }

    public function passToOtherLogger(LoggerInterface $logger): void
    {
        foreach ($this->messages as $message) {
            $logger->{$message['level']}($message['message']);
        }
    }
}
