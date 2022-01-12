<?php

namespace Crwlr\Crawler\Logger;

use DateTime;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use UnexpectedValueException;

class CliLogger implements LoggerInterface
{
    public function emergency($message, array $context = array())
    {
        $this->log('emergency', $message, $context);
    }

    public function alert($message, array $context = array())
    {
        $this->log('alert', $message, $context);
    }

    public function critical($message, array $context = array())
    {
        $this->log('critical', $message, $context);
    }

    public function error($message, array $context = array())
    {
        $this->log('error', $message, $context);
    }

    public function warning($message, array $context = array())
    {
        $this->log('warning', $message, $context);
    }

    public function notice($message, array $context = array())
    {
        $this->log('notice', $message, $context);
    }

    public function info($message, array $context = array())
    {
        $this->log('info', $message, $context);
    }

    public function debug($message, array $context = array())
    {
        $this->log('debug', $message, $context);
    }

    public function log($level, $message, array $context = array())
    {
        if (!is_string($level)) {
            throw new InvalidArgumentException('Level must be string.');
        }

        if (!in_array($level, ['emergency', 'alert', 'critical', 'error', 'warning', 'notice', 'info', 'debug'])) {
            throw new UnexpectedValueException('Unknown log level.');
        }

        echo $this->printTimeAndLevel($level) . $message . "\n";
    }

    private function printTimeAndLevel(string $level): void
    {
        echo $this->time() . " \033[0;" . $this->levelColor($level) . "m[" . strtoupper($level) . "]\033[0m ";
    }

    private function time(): string
    {
        return (new DateTime())->format('H:i:s:u');
    }

    private function levelColor(string $level): string
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
