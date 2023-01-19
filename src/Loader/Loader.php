<?php

namespace Crwlr\Crawler\Loader;

use Crwlr\Crawler\Logger\CliLogger;
use Crwlr\Crawler\UserAgents\UserAgentInterface;
use Psr\Http\Message\UriInterface;
use Psr\Log\LoggerInterface;
use Psr\SimpleCache\CacheInterface;

abstract class Loader implements LoaderInterface
{
    protected LoggerInterface $logger;

    protected ?CacheInterface $cache = null;

    /**
     * @var array<string, callable[]>
     */
    protected array $hooks = [
        'beforeLoad' => [],
        'onSuccess' => [],
        'onError' => [],
        'afterLoad' => [],
    ];

    public function __construct(
        protected UserAgentInterface $userAgent,
        ?LoggerInterface $logger = null,
    ) {
        $this->logger = $logger ?? new CliLogger();
    }

    public function beforeLoad(callable $callback): void
    {
        $this->addHookCallback('beforeLoad', $callback);
    }

    public function onSuccess(callable $callback): void
    {
        $this->addHookCallback('onSuccess', $callback);
    }

    public function onError(callable $callback): void
    {
        $this->addHookCallback('onError', $callback);
    }

    public function afterLoad(callable $callback): void
    {
        $this->addHookCallback('afterLoad', $callback);
    }

    public function setCache(CacheInterface $cache): void
    {
        $this->cache = $cache;
    }

    /**
     * Can be implemented in a child class to check if it is allowed to load a certain uri (e.g. check robots.txt)
     * Throw a LoadingException when it's not allowed and $throwsException is set to true.
     */
    protected function isAllowedToBeLoaded(UriInterface $uri, bool $throwsException = false): bool
    {
        return true;
    }

    /**
     * Can be implemented in a child class to track how long a request waited for its response.
     */
    protected function trackRequestStart(?float $microtime = null): void
    {
    }

    /**
     * Can be implemented in a child class to track how long a request waited for its response.
     */
    protected function trackRequestEnd(?float $microtime = null): void
    {
    }

    protected function callHook(string $hook, mixed ...$arguments): void
    {
        if (!array_key_exists($hook, $this->hooks)) {
            return;
        }

        $arguments[] = $this->logger;

        foreach ($this->hooks[$hook] as $callback) {
            call_user_func($callback, ...$arguments);
        }
    }

    public function userAgent(): UserAgentInterface
    {
        return $this->userAgent;
    }

    protected function logger(): LoggerInterface
    {
        return $this->logger;
    }

    protected function addHookCallback(string $hook, callable $callback): void
    {
        $this->hooks[$hook][] = $callback;
    }
}
