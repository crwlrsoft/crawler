<?php

namespace Crwlr\Crawler\Loader\Http\Traits;

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

    /**
     * Filled when trackRequestStart() is called.
     * Reset when trackRequestEnd() is called.
     */
    private float $currentRequestStartTimestamp = 0.0;

    /**
     * The timestamp when the latest response was fully received.
     */
    protected float $latestResponseTimestamp = 0.0;

    /**
     * The time between the request was sent and the response was received of the latest request.
     */
    protected float $latestRequestResponseDuration = 0.0;

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

    protected function trackRequestStart(?float $microtime = null): void
    {
        $this->currentRequestStartTimestamp = $microtime ?? $this->time();
    }

    protected function trackRequestEnd(?float $microtime = null): void
    {
        if ($this->currentRequestStartTimestamp === 0.0) {
            return;
        }

        $this->latestResponseTimestamp = $microtime ?? $this->time();
        $this->latestRequestResponseDuration = $this->latestResponseTimestamp - $this->currentRequestStartTimestamp;
        $this->currentRequestStartTimestamp = 0.0;
    }

    protected function waitUntilNextRequestCanBeSent(): void
    {
        if ($this->latestRequestResponseDuration === 0.0) {
            return;
        }

        $waitUntil = $this->calcWaitUntilTimestamp();
        $now = microtime(true);

        if ($now >= $waitUntil) { // Don't need to wait.
            return;
        }

        $wait = $waitUntil - $now;
        $this->logger->info('Wait ' . round($wait, 4) . 's for politeness.');
        usleep((int) ($wait * 1000000));
    }

    protected function time(): float
    {
        return microtime(true);
    }

    private function calcWaitUntilTimestamp(): float
    {
        $waitTime = $this->getWaitXTimesOfPreviousResponseTime() * $this->latestRequestResponseDuration;

        if ($waitTime < $this->minWaitTime) {
            $waitTime = $this->minWaitTime;
        }

        return $this->latestResponseTimestamp + $waitTime;
    }

    private function getWaitXTimesOfPreviousResponseTime(): float
    {
        if ($this->waitXTimesOfPreviousResponseTime['from'] === $this->waitXTimesOfPreviousResponseTime['to']) {
            return $this->waitXTimesOfPreviousResponseTime['from'];
        }

        return rand(
            (int) ($this->waitXTimesOfPreviousResponseTime['from'] * 1000000),
            (int) ($this->waitXTimesOfPreviousResponseTime['to'] * 1000000)
        ) / 1000000;
    }
}
