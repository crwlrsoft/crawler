<?php

namespace Crwlr\Crawler\Loader\Http\Politeness;

use Closure;
use Crwlr\Crawler\Loader\Http\Exceptions\LoadingException;
use Crwlr\Crawler\Loader\Http\Messages\RespondedRequest;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;

class TooManyRequestsHandler
{
    /**
     * @param int[] $wait
     */
    public function __construct(
        protected int $retries = 2,
        protected array $wait = [10, 60],
        protected int $maxWait = 60,
    ) {
    }

    /**
     * @throws LoadingException
     */
    public function handleRetries(
        RespondedRequest $respondedRequest,
        Closure $retryCallback,
        ?LoggerInterface $logger = null
    ): RespondedRequest {
        $logger?->warning(
            'Request to ' . $respondedRequest->requestedUri() . ' returned 429 - Too Many Requests.'
        );

        $retries = 0;

        $this->wait[0] = $this->getWaitTimeFromResponse($respondedRequest->response, $logger) ?? $this->wait[0];

        while ($retries < $this->retries) {
            $logger?->warning('Will wait for ' . $this->wait[$retries] . ' seconds and then retry');

            sleep($this->wait[$retries]);

            $respondedRequest = $retryCallback();

            if ($respondedRequest instanceof RespondedRequest && $respondedRequest->response->getStatusCode() !== 429) {
                return $respondedRequest;
            } else {
                $logger?->warning('Retry received a 429 response again');
            }

            $retries++;
        }

        $logger?->error('Stop crawling');

        return throw new LoadingException('Stopped crawling because server responds with 429 - Too Many Requests');
    }

    protected function getWaitTimeFromResponse(ResponseInterface $response, ?LoggerInterface $logger = null): ?int
    {
        $retryAfterHeader = $response->getHeader('Retry-After');

        if (!empty($retryAfterHeader)) {
            $retryAfterHeader = reset($retryAfterHeader);

            if (is_numeric($retryAfterHeader)) {
                $waitFor = (int) $retryAfterHeader;

                if ($waitFor > $this->maxWait) {
                    $message = 'Retry-After header in 429 (Too Many Requests) response, requires to wait longer ' .
                        'than the defined max wait time for this case. If you want to increase this limit, set it ' .
                        'in the TooManyRequestsHandler of your HttpLoader instance.';

                    $logger?->error($message);

                    throw new LoadingException($message);
                }

                return (int) $retryAfterHeader;
            }
        }

        return null;
    }
}
