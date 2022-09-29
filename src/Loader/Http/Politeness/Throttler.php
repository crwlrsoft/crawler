<?php

namespace Crwlr\Crawler\Loader\Http\Politeness;

use Crwlr\Crawler\Loader\Http\Politeness\TimingUnits\Microseconds;
use Crwlr\Crawler\Loader\Http\Politeness\TimingUnits\MultipleOf;
use Crwlr\Url\Url;
use InvalidArgumentException;
use Psr\Http\Message\UriInterface;
use Psr\Log\LoggerInterface;

class Throttler
{
    /**
     * @var array<string, Microseconds>
     */
    private array $latestRequestTimes = [];

    /**
     * @var array<string, Microseconds>
     */
    private array $latestResponseTimes = [];

    /**
     * @var array<string, Microseconds>
     */
    private array $latestDurations = [];

    protected Microseconds|MultipleOf $from;
    protected Microseconds|MultipleOf $to;
    protected Microseconds $min;
    protected ?LoggerInterface $logger = null;

    /**
     * @throws InvalidArgumentException
     */
    public function __construct(
        Microseconds|MultipleOf|null $from = null,
        Microseconds|MultipleOf|null $to = null,
        Microseconds|null $min = null,
        protected Microseconds|null $max = null,
    ) {
        $this->from = $from ?? new MultipleOf(1.0);

        $this->to = $to ?? new MultipleOf(2.0);

        $this->validateFromAndTo();

        $this->min = $min ?? Microseconds::fromSeconds(0.25);
    }

    /**
     * @throws InvalidArgumentException
     */
    public function waitBetween(Microseconds|MultipleOf $from, Microseconds|MultipleOf $to): static
    {
        $this->from = $from;

        $this->to = $to;

        $this->validateFromAndTo();

        return $this;
    }

    public function waitAtLeast(Microseconds $seconds): static
    {
        $this->min = $seconds;

        return $this;
    }

    public function waitAtMax(Microseconds $seconds): static
    {
        $this->max = $seconds;

        return $this;
    }

    public function trackRequestStartFor(UriInterface $url): void
    {
        $domain = $this->getDomain($url);

        $this->latestRequestTimes[$domain] = $this->time();
    }

    public function trackRequestEndFor(UriInterface $url): void
    {
        $domain = $this->getDomain($url);

        if (!isset($this->latestRequestTimes[$domain])) {
            return;
        }

        $this->latestResponseTimes[$domain] = $responseTime = $this->time();

        $this->latestDurations[$domain] = $responseTime->subtract($this->latestRequestTimes[$domain]);

        unset($this->latestRequestTimes[$domain]);
    }

    public function waitForGo(UriInterface $url): void
    {
        $domain = $this->getDomain($url);

        if (!isset($this->latestDurations[$domain])) {
            return;
        }

        $waitUntil = $this->calcWaitUntil($domain);

        $now = $this->time();

        if ($now->isGreaterThanOrEqual($waitUntil)) {
            return;
        }

        $wait = $waitUntil->subtract($now);

        usleep($wait->value);
    }

    public function addLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }

    protected function time(): Microseconds
    {
        return Microseconds::fromSeconds(microtime(true));
    }

    protected function getDomain(UriInterface $url): string
    {
        $domain = Url::parse($url)->domain();

        if (!$domain) {
            $domain = $url->getHost();
        }

        if (!is_string($domain)) {
            $domain = '*';
        }

        return $domain;
    }

    private function calcWaitUntil(string $domain): Microseconds
    {
        $latestResponseDuration = $this->latestDurations[$domain];

        $from = $this->from instanceof MultipleOf ? $this->from->calc($latestResponseDuration) : $this->from;

        $to = $this->to instanceof MultipleOf ? $this->to->calc($latestResponseDuration) : $this->to;

        $waitValue = $this->getRandBetween($from, $to);

        if ($this->min->isGreaterThan($waitValue)) {
            $waitValue = $this->min;
        }

        if ($this->max && $this->max->isLessThan($waitValue)) {
            $waitValue = $this->max;
        }

        return $this->latestResponseTimes[$domain]->add($waitValue);
    }

    private function getRandBetween(Microseconds $from, Microseconds $to): Microseconds
    {
        if ($from->equals($to)) {
            return $from;
        }

        return new Microseconds(rand($from->value, $to->value));
    }

    private function validateFromAndTo(): void
    {
        if (!$this->fromAndToAreOfSameType()) {
            throw new InvalidArgumentException('From and to values must be of the same type (Seconds or MultipleOf).');
        }

        if ($this->fromIsGreaterThanTo()) {
            throw new InvalidArgumentException('From value can\'t be greater than to value.');
        }
    }

    protected function fromAndToAreOfSameType(): bool
    {
        return ($this->from instanceof Microseconds && $this->to instanceof Microseconds) ||
            ($this->from instanceof MultipleOf && $this->to instanceof MultipleOf);
    }

    protected function fromIsGreaterThanTo(): bool
    {
        if ($this->from instanceof Microseconds && $this->to instanceof Microseconds) {
            return $this->from->isGreaterThan($this->to);
        }

        if ($this->from instanceof MultipleOf && $this->to instanceof MultipleOf) {
            return $this->from->factorIsGreaterThan($this->to);
        }

        return false;
    }
}
