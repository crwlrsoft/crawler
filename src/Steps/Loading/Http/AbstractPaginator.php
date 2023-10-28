<?php

namespace Crwlr\Crawler\Steps\Loading\Http;

use Closure;
use Crwlr\Crawler\Loader\Http\Messages\RespondedRequest;
use Crwlr\Crawler\Steps\Loading\Http\Paginators\StopRules\StopRule;
use Crwlr\Crawler\Utils\RequestKey;
use Crwlr\Url\Url;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\UriInterface;
use Psr\Log\LoggerInterface;

abstract class AbstractPaginator
{
    /**
     * @var array<string, true>
     */
    protected array $loaded = [];

    protected int $loadedCount = 0;

    protected ?RequestInterface $latestRequest;

    /**
     * @var array<int, Closure|StopRule>
     */
    protected array $stopRules = [];

    protected bool $hasFinished = false;

    public function __construct(protected int $maxPages = Paginator::MAX_PAGES_DEFAULT) {}

    public function processLoaded(
        UriInterface $url,
        RequestInterface $request,
        ?RespondedRequest $respondedRequest,
    ): void {
        $this->registerLoadedRequest($respondedRequest ?? $request);
    }

    public function hasFinished(): bool
    {
        return $this->hasFinished || $this->maxPagesReached();
    }

    public function stopWhen(Closure|StopRule $callback): self
    {
        $this->stopRules[] = $callback;

        return $this;
    }

    /**
     * Default implementation of getNextRequest() that will be remove in v2.
     * Initially it was required that an implementation has a getNextUrl() method.
     * As paginating is not always only done via the URL, it's better to have a getNextRequest() method
     * to be more flexible. Until v2 of this library this method makes the next request, using the
     * getNextUrl() method. In v2 it will then be required, that Paginator implementations, implement
     * their own getNextRequest() method and getNextUrl() won't be required anymore.
     */
    public function getNextRequest(): ?RequestInterface
    {
        if (!$this->latestRequest || !method_exists($this, 'getNextUrl')) {
            return null;
        }

        $nextUrl = $this->getNextUrl();

        if (!$nextUrl) {
            return null;
        }

        return $this->latestRequest->withUri(Url::parsePsr7($nextUrl));
    }

    public function logWhenFinished(LoggerInterface $logger): void
    {
        if ($this->maxPagesReached()) {
            $logger->notice('Max pages limit reached.');
        } else {
            $logger->info('Finished paginating.');
        }
    }

    /**
     * For v2. See above.
     */
    //abstract public function getNextRequest(): ?RequestInterface;

    protected function registerLoadedRequest(RequestInterface|RespondedRequest $request): void
    {
        $key = $request instanceof RespondedRequest ? RequestKey::from($request->request) : RequestKey::from($request);

        if (array_key_exists($key, $this->loaded)) {
            return;
        }

        $this->loaded[$key] = true;

        $this->loadedCount++;

        if ($request instanceof RespondedRequest) {
            foreach ($request->redirects() as $redirectUrl) {
                $this->loaded[RequestKey::from($request->request->withUri(Url::parsePsr7($redirectUrl)))] = true;
            }
        }

        $this->latestRequest = $request instanceof RespondedRequest ? $request->request : $request;

        $respondedRequest = $request instanceof RespondedRequest ? $request : null;

        $request = $request instanceof RequestInterface ? $request : $request->request;

        if ($this->shouldStop($request, $respondedRequest)) {
            $this->setFinished();
        }
    }

    protected function shouldStop(RequestInterface $request, ?RespondedRequest $respondedRequest): bool
    {
        if ($this->maxPagesReached()) {
            return true;
        }

        foreach ($this->stopRules as $stopRule) {
            if ($stopRule instanceof StopRule && $stopRule->shouldStop($request, $respondedRequest)) {
                return true;
            } elseif ($stopRule instanceof Closure && $stopRule->call($this, $request, $respondedRequest)) {
                return true;
            }
        }

        return false;
    }

    protected function maxPagesReached(): bool
    {
        return $this->loadedCount >= $this->maxPages;
    }

    protected function setFinished(): self
    {
        $this->hasFinished = true;

        return $this;
    }
}
