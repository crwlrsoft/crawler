<?php

namespace Crwlr\Crawler\Loader;

use Crwlr\Crawler\UserAgent;
use Psr\Http\Message\UriInterface;
use Psr\Log\LoggerInterface;
use Psr\SimpleCache\CacheInterface;

abstract class Loader implements LoaderInterface
{
    protected array $hooks = [
        'beforeLoad' => null,
        'onSuccess' => null,
        'onError' => null,
        'afterLoad' => null,
    ];

    public function __construct(
        protected UserAgent $userAgent,
        protected LoggerInterface $logger,
        protected ?CacheInterface $cache = null,
    ) {
    }

    public function beforeLoad(callable $callback)
    {
        $this->addHookCallback('beforeLoad', $callback);
    }

    public function onSuccess(callable $callback)
    {
        $this->addHookCallback('onSuccess', $callback);
    }

    public function onError(callable $callback)
    {
        $this->addHookCallback('onError', $callback);
    }

    public function afterLoad(callable $callback)
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
    protected function trackRequestStart(): void
    {
    }

    /**
     * Can be implemented in a child class to track how long a request waited for its response.
     */
    protected function trackRequestEnd(): void
    {
    }

    protected function callHook(string $hook, ...$arguments): void
    {
        $arguments[] = $this->logger;

        if (array_key_exists($hook, $this->hooks) && is_array($this->hooks[$hook])) {
            foreach ($this->hooks[$hook] as $callback) {
                call_user_func($callback, ...$arguments);
            }
        }
    }

    protected function userAgent(): UserAgent
    {
        return $this->userAgent;
    }

    protected function logger(): LoggerInterface
    {
        return $this->logger;
    }

    private function addHookCallback(string $hook, callable $callback)
    {
        if ($this->hooks[$hook] === null) {
            $this->hooks[$hook] = [$callback];
        } else {
            $this->hooks[$hook][] = $callback;
        }
    }
}
