<?php

namespace Crwlr\Crawler\Loader\Http\Politeness;

use Closure;
use Crwlr\Crawler\Loader\Http\Exceptions\LoadingException;
use Crwlr\Crawler\Loader\Http\Messages\RespondedRequest;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;

class RetryErrorResponseHandler
{
    protected ?LoggerInterface $logger = null;

    /**
     * @var array<int, string>
     */
    protected array $waitErrors = [
        429 => 'Too many Requests',
        503 => 'Service Unavailable',
    ];

    /**
     * @param int[] $wait
     */
    public function __construct(
        protected int $retries = 2,
        protected array $wait = [10, 60],
        protected int $maxWait = 60,
    ) {}

    public function shouldWait(RespondedRequest $respondedRequest): bool
    {
        if (array_key_exists($respondedRequest->response->getStatusCode(), $this->waitErrors)) {
            return true;
        }

        return false;
    }

    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }

    /**
     * @throws LoadingException
     */
    public function handleRetries(
        RespondedRequest $respondedRequest,
        Closure $retryCallback,
    ): RespondedRequest {
        $this->logReceivedErrorResponseMessage($respondedRequest);

        $retries = 0;

        $this->wait[0] = $this->getWaitTimeFromResponse($respondedRequest->response) ?? $this->wait[0];

        while ($retries < $this->retries) {
            $this->logWaitForRetryMessage($retries);

            sleep($this->wait[$retries]);

            $respondedRequest = $retryCallback();

            if ($respondedRequest instanceof RespondedRequest && !$this->shouldWait($respondedRequest)) {
                return $respondedRequest;
            } elseif ($respondedRequest) {
                $this->logRepeatedErrorMessage($respondedRequest);
            }

            $retries++;
        }

        $this->logger?->error('Stop crawling');

        throw new LoadingException('Stopped crawling because of repeated error responses.');
    }

    /**
     * @throws LoadingException
     */
    protected function getWaitTimeFromResponse(ResponseInterface $response): ?int
    {
        $retryAfterHeader = $response->getHeader('Retry-After');

        if (!empty($retryAfterHeader)) {
            $retryAfterHeader = reset($retryAfterHeader);

            if (is_numeric($retryAfterHeader)) {
                $waitFor = (int) $retryAfterHeader;

                if ($waitFor > $this->maxWait) {
                    $this->retryAfterExceedsLimitMessage($response);
                }

                return (int) $retryAfterHeader;
            }
        }

        return null;
    }

    protected function getResponseCodeAndReasonPhrase(RespondedRequest|ResponseInterface $respondedRequest): string
    {
        $response = $respondedRequest instanceof RespondedRequest ? $respondedRequest->response : $respondedRequest;

        $statusCode = $response->getStatusCode();

        if (array_key_exists($statusCode, $this->waitErrors)) {
            return $statusCode . ' (' . $this->waitErrors[$statusCode] . ')';
        }

        return '?';
    }

    protected function logReceivedErrorResponseMessage(RespondedRequest $respondedRequest): void
    {
        $statusCodeAndReasonPhrase = $this->getResponseCodeAndReasonPhrase($respondedRequest);

        $this->logger?->warning(
            'Request to ' . $respondedRequest->requestedUri() . ' returned ' . $statusCodeAndReasonPhrase
        );
    }

    protected function logWaitForRetryMessage(int $retryNumber): void
    {
        $this->logger?->warning('Will wait for ' . $this->wait[$retryNumber] . ' seconds and then retry');
    }

    protected function logRepeatedErrorMessage(RespondedRequest $respondedRequest): void
    {
        $statusCodeAndReasonPhrase = $this->getResponseCodeAndReasonPhrase($respondedRequest);

        $this->logger?->warning('Retry again received an error response: ' . $statusCodeAndReasonPhrase);
    }

    /**
     * @throws LoadingException
     */
    protected function retryAfterExceedsLimitMessage(ResponseInterface $response): string
    {
        $statusCodeAndReasonPhrase = $this->getResponseCodeAndReasonPhrase($response);

        $message = 'Retry-After header in ' . $statusCodeAndReasonPhrase . ' response, requires to wait longer ' .
            'than the defined max wait time for this case. If you want to increase this limit, set it ' .
            'in the ErrorResponseHandler of your HttpLoader instance.';

        $this->logger?->error($message);

        throw new LoadingException($message);
    }
}
