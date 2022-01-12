<?php

namespace Crwlr\Crawler\Loader\Traits;

use UnexpectedValueException;

trait WaitPolitely
{
    /**
     * This adds functionality to wait between two requests for reasons of politeness.
     *
     * As the time websites (or other sources) take to respond can differ significantly, the time to wait until the
     * next request, is based on the time the server needed for the previous response to be delivered.
     *
     * To also have some randomness we (can) define a from and to value here.
     * So for example for a previous response time of 100ms, the default of from => 1.0 and to => 2.0 means that it
     * will wait between 100 and 200 ms from the time of the previous response until it sends the next request.
     *
     * @var array|float[]
     */
    private array $waitXTimesOfPreviousResponseTime = [
        'from' => 0.25,
        'to' => 0.5,
    ];

    /**
     * In any case wait at least this long (in seconds, so this is 10 ms).
     *
     * @var float
     */
    private float $minWaitTime = 0.01;

    private float $currentRequestStartedAtTimestamp = 0.0;

    /**
     * The timestamp when the previous response was received.
     */
    protected float $previousResponseTimestamp = 0.0;

    /**
     * The time between the request was sent and the response was received of the latest request.
     */
    protected float $previousResponseTime = 0.0;

    public function trackStartSendingRequest(): void
    {
        $this->currentRequestStartedAtTimestamp = $this->time();
    }

    public function trackRequestFinished(): void
    {
        if ($this->currentRequestStartedAtTimestamp === 0.0) {
            return;
        }

        $this->previousResponseTimestamp = $this->time();
        $this->previousResponseTime = $this->previousResponseTimestamp - $this->currentRequestStartedAtTimestamp;
        $this->currentRequestStartedAtTimestamp = 0.0;
    }

    public function setWaitXTimesOfPreviousResponseTime(float $from, float $to): void
    {
        if ($to < $from) {
            throw new UnexpectedValueException('Argument to must be greater than or equal from.');
        }

        $this->waitXTimesOfPreviousResponseTime['from'] = max($from, 0);
        $this->waitXTimesOfPreviousResponseTime['to'] = max($to, 0);
    }

    public function setMinWaitTime(float $minWaitTime): void
    {
        $this->minWaitTime = $minWaitTime;
    }

    protected function waitUntilNextRequestCanBeSent(): void
    {
        if ($this->previousResponseTime === 0.0) {
            return;
        }

        $waitUntil = $this->calcWaitUntilTimestamp();
        $now = microtime(true);

        if ($now >= $waitUntil) { // Don't need to wait.
            return;
        }

        $wait = $waitUntil - $now;
        $this->logger->info('Wait ' . round($wait, 3) . 's for politeness.');
        usleep($wait * 1000000);
    }

    protected function time(): float
    {
        return microtime(true);
    }

    private function calcWaitUntilTimestamp(): float
    {
        $waitTime = $this->getWaitXTimesOfPreviousResponseTime() * $this->previousResponseTime;

        if ($waitTime < $this->minWaitTime) {
            $waitTime = $this->minWaitTime;
        }

        return $this->previousResponseTimestamp + $waitTime;
    }

    private function getWaitXTimesOfPreviousResponseTime(): float
    {
        if ($this->waitXTimesOfPreviousResponseTime['from'] === $this->waitXTimesOfPreviousResponseTime['to']) {
            return $this->waitXTimesOfPreviousResponseTime['from'];
        }

        return rand(
                $this->waitXTimesOfPreviousResponseTime['from'] * 1000000,
                $this->waitXTimesOfPreviousResponseTime['to'] * 1000000
            ) / 1000000;
    }
}
