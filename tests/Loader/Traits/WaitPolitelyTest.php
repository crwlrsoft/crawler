<?php

namespace tests\Loader\Traits;

use Crwlr\Crawler\Aggregates\RequestResponseAggregate;
use Crwlr\Crawler\Loader\HttpLoader;
use Crwlr\Crawler\Loader\Traits\WaitPolitely;
use Crwlr\Crawler\UserAgent;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Client\ClientInterface;
use Psr\Log\LoggerInterface;

/**
 * @return HttpLoader
 */
function helper_getLoaderWhereFirstRequestTakes100Milliseconds(): HttpLoader
{
    // Built here so it won't take time to build a new response in the load method for the timing tests.
    $response = new RequestResponseAggregate(new Request('GET', '/'), new Response());

    return new class(new UserAgent('FooBot'), $response) extends HttpLoader {
        use WaitPolitely;

        public function __construct(
            UserAgent $userAgent,
            private RequestResponseAggregate $response,
            ?ClientInterface $httpClient = null,
            ?LoggerInterface $logger = null
        ) {
            parent::__construct($userAgent, $httpClient, $logger);
        }

        public function load(mixed $subject): ?RequestResponseAggregate
        {
            $this->waitUntilNextRequestCanBeSent();

            if ($this->latestRequestResponseDuration === 0.0) { // let the first request take 0.1 seconds
                $start = microtime(true);
                $this->trackRequestStart($start);
                while ((microtime(true) - $start) < 0.1) {
                }
                $this->trackRequestEnd(microtime(true));
            }

            return $this->response;
        }
    };
}

test(
    'When waitUntilNextRequestCanBeSent it waits per default between 0.25 and 0.5 times of the time the last request ' .
    'took to be delivered',
    function () {
        $loader = helper_getLoaderWhereFirstRequestTakes100Milliseconds();

        $loader->load('https://www.crwlr.software');
        $firstResponse = microtime(true);
        $loader->load('https://www.crwlr.software/contact');
        $secondResponse = microtime(true);

        $diffInSeconds = round($secondResponse - $firstResponse, 5);
        expect($diffInSeconds)->toBeGreaterThan(0.025);
        expect($diffInSeconds)->toBeLessThanOrEqual(0.056); // 0.056 because other things could also take a few ms
    }
);

test('You can set your own wait x times of previous request range', function () {
    $loader = helper_getLoaderWhereFirstRequestTakes100Milliseconds();
    $loader->setWaitXTimesOfPreviousResponseTime(1, 2); // @phpstan-ignore-line

    $loader->load('https://www.crwlr.software');
    $firstResponse = microtime(true);
    $loader->load('https://www.crwlr.software/contact');
    $secondResponse = microtime(true);

    $diffInSeconds = round($secondResponse - $firstResponse, 5);
    expect($diffInSeconds)->toBeGreaterThan(0.1);
    expect($diffInSeconds)->toBeLessThanOrEqual(0.206); // 0.206 because other things could also take a few ms
});

test('It also respects the min wait time', function () {
    $loader = helper_getLoaderWhereFirstRequestTakes100Milliseconds();
    $loader->setWaitXTimesOfPreviousResponseTime(0.1, 0.1); // @phpstan-ignore-line
    $loader->setMinWaitTime(0.05); // @phpstan-ignore-line

    $loader->load('https://www.crwlr.software');
    $firstResponse = microtime(true);
    $loader->load('https://www.crwlr.software/contact');
    $secondResponse = microtime(true);

    $diffInSeconds = round($secondResponse - $firstResponse, 5);
    expect($diffInSeconds)->toBeGreaterThan(0.05);
});
