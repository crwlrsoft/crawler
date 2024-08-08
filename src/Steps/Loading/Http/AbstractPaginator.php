<?php

namespace Crwlr\Crawler\Steps\Loading\Http;

use Closure;
use Crwlr\Crawler\Loader\Http\Messages\RespondedRequest;
use Crwlr\Crawler\Steps\Loading\Http\Paginators\StopRules\StopRule;
use Crwlr\Crawler\Utils\RequestKey;
use Crwlr\Url\Url;
use Psr\Http\Message\RequestInterface;
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
        RequestInterface $request,
        ?RespondedRequest $respondedRequest,
    ): void {
        $this->registerLoadedRequest($respondedRequest ?? $request);
    }

    public function hasFinished(): bool
    {
        return $this->hasFinished || $this->maxPagesReached();
    }

    /**
     * When a paginate step is called with multiple inputs, like:
     *
     * ['https://www.example.com/listing1', 'https://www.example.com/listing2', ...]
     *
     * it always has to start paginating again for each listing base URL.
     * Therefore, we reset the state after finishing paginating one base input.
     * Except for $this->found, because if it would be the case that the exact same pages are
     * discovered whilst paginating, we don't want to load the exact same pages again and again.
     */
    public function resetFinished(): void
    {
        $this->hasFinished = false;

        $this->loadedCount = 0;

        $this->latestRequest = null;
    }

    public function stopWhen(Closure|StopRule $callback): self
    {
        $this->stopRules[] = $callback;

        return $this;
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
    abstract public function getNextRequest(): ?RequestInterface;

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
