<?php

namespace Crwlr\Crawler\Logger;

use DateTime;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Stringable;
use UnexpectedValueException;

class CliLogger implements LoggerInterface
{
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

        $this->printTimeAndLevel($level);
        echo $message . "\n";
    }

    protected function printTimeAndLevel(string $level): void
    {
        echo $this->time() . " \033[0;" . $this->levelColor($level) . "m[" . strtoupper($level) . "]\033[0m ";
    }

    protected function time(): string
    {
        return (new DateTime())->format('H:i:s:u');
    }

    protected function levelColor(string $level): string
    {
        $levelColors = [
            'emergency' => '91', // bright red
            'alert' => '91',
            'critical' => '91',
            'error' => '31',     // red
            'warning' => '36',   // cyan
            'notice' => '34',    // blue
            'info' => '32',      // green
            'debug' => '33',     // yellow
        ];

        return $levelColors[$level];
    }
}
