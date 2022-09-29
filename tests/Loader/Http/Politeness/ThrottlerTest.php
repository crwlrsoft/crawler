<?php

namespace tests\Loader\Http\Politeness;

use Crwlr\Crawler\Loader\Http\Politeness\Throttler;
use Crwlr\Crawler\Loader\Http\Politeness\TimingUnits\Microseconds;
use Crwlr\Crawler\Loader\Http\Politeness\TimingUnits\MultipleOf;
use Crwlr\Url\Url;
use InvalidArgumentException;

it('waits between 1.0 and 2.0 times of the time span that the last request took by default', function () {
    $url = Url::parsePsr7('https://www.example.com');

    $throttler = new Throttler();

    $throttler->waitAtLeast(Microseconds::fromSeconds(0.001));

    $throttler->trackRequestStartFor($url);

    usleep(Microseconds::fromSeconds(0.1)->value);

    $throttler->trackRequestEndFor($url);

    $requestEndTime = Microseconds::fromSeconds(microtime(true));

    $throttler->waitForGo($url);

    $readyForNextRequest = Microseconds::fromSeconds(microtime(true));

    $diff = $readyForNextRequest->subtract($requestEndTime);

    expect($diff->value)->toBeGreaterThan(100000);

    expect($diff->value)->toBeLessThan(220000); // A bit more than * 2.0 because other things happening also take time.
});

it('waits min 0.25s by default', function () {
    $url = Url::parsePsr7('https://www.example.com');

    $throttler = new Throttler();

    $throttler->trackRequestStartFor($url);

    $throttler->trackRequestEndFor($url);

    $requestEndTime = Microseconds::fromSeconds(microtime(true));

    $throttler->waitForGo($url);

    $readyForNextRequest = Microseconds::fromSeconds(microtime(true));

    $diff = $readyForNextRequest->subtract($requestEndTime);

    expect($diff->value)->toBeGreaterThan(250000);
});

it('respects the max wait time you set', function () {
    $url = Url::parsePsr7('https://www.example.com');

    $throttler = new Throttler();

    $throttler
        ->waitBetween(new MultipleOf(10), new MultipleOf(20))
        ->waitAtMax(Microseconds::fromSeconds(0.1));

    $throttler->trackRequestStartFor($url);

    usleep(Microseconds::fromSeconds(0.1)->value);

    $throttler->trackRequestEndFor($url);

    $requestEndTime = Microseconds::fromSeconds(microtime(true));

    $throttler->waitForGo($url);

    $readyForNextRequest = Microseconds::fromSeconds(microtime(true));

    $diff = $readyForNextRequest->subtract($requestEndTime);

    expect($diff->value)->toBeLessThan(110000); // A bit more than * 1.0 because other things happening also take time.
});

it('waits only if there was already a request to the same domain', function () {
    $url = Url::parsePsr7('https://www.example.com');

    $throttler = new Throttler();

    $throttler
        ->waitBetween(new MultipleOf(10), new MultipleOf(20))
        ->waitAtMax(Microseconds::fromSeconds(0.1));

    $throttler->trackRequestStartFor($url);

    usleep(Microseconds::fromSeconds(0.01)->value);

    $throttler->trackRequestEndFor($url);

    $requestEndTime = Microseconds::fromSeconds(microtime(true));

    $throttler->waitForGo(Url::parsePsr7('https://www.crwlr.software'));

    $readyForNextRequest = Microseconds::fromSeconds(microtime(true));

    $diff = $readyForNextRequest->subtract($requestEndTime);

    expect($diff->value)->toBeLessThan(1000);
});

it('throws an exception if you try to set different types for from and to', function () {
    new Throttler(Microseconds::fromSeconds(0.1), new MultipleOf(0.5));
})->throws(InvalidArgumentException::class);

it('throws an exception if you try to set the from value bigger than the to value with Microseconds', function () {
    new Throttler(Microseconds::fromSeconds(2.0), Microseconds::fromSeconds(1.0));
})->throws(InvalidArgumentException::class);

it('throws an exception if you try to set the from value bigger than the to value with MultipleOf', function () {
    new Throttler(new MultipleOf(1.0), new MultipleOf(0.9));
})->throws(InvalidArgumentException::class);

it('does not throw an exception when from and to values are equal', function () {
    new Throttler(Microseconds::fromSeconds(2.0), Microseconds::fromSeconds(2.0));

    new Throttler(new MultipleOf(1.0), new MultipleOf(1.0));

    expect(true)->toBeTrue();
});
