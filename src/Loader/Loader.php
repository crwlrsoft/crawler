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
        'onCacheHit' => [],
        'onSuccess' => [],
        'onError' => [],
        'afterLoad' => [],
    ];

    /**
     * @var array<string, bool>
     */
    private array $_hooksCalledInCurrentLoadCall = [];

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

    public function onCacheHit(callable $callback): void
    {
        $this->addHookCallback('onCacheHit', $callback);
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

    public function setCache(CacheInterface $cache): static
    {
        $this->cache = $cache;

        return $this;
    }

    public function userAgent(): UserAgentInterface
    {
        return $this->userAgent;
    }

    /**
     * Can be implemented in a child class to check if it is allowed to load a certain uri (e.g. check robots.txt)
     * Throw a LoadingException when it's not allowed and $throwsException is set to true.
     */
    protected function isAllowedToBeLoaded(UriInterface $uri, bool $throwsException = false): bool
    {
        return true;
    }

    protected function callHook(string $hook, mixed ...$arguments): void
    {
        if (!array_key_exists($hook, $this->hooks)) {
            return;
        }

        if (array_key_exists($hook, $this->_hooksCalledInCurrentLoadCall)) {
            $this->logger->warning(
                $hook . ' was already called in this load call. Probably a problem in the loader implementation.',
            );
        }

        if (
            $hook === 'afterLoad' &&
            !empty($this->hooks[$hook]) &&
            !array_key_exists('beforeLoad', $this->_hooksCalledInCurrentLoadCall)
        ) {
            $this->logger->warning(
                'The afterLoad hook was called without a preceding call to the beforeLoad hook. Therefore don\'t ' .
                'run the hook callbacks. Most likely an exception/error occurred  before the beforeLoad hook call.',
            );

            return;
        }

        $arguments[] = $this->logger;

        foreach ($this->hooks[$hook] as $callback) {
            call_user_func($callback, ...$arguments);
        }

        $this->_hooksCalledInCurrentLoadCall[$hook] = true;
    }

    protected function logger(): LoggerInterface
    {
        return $this->logger;
    }

    protected function addHookCallback(string $hook, callable $callback): void
    {
        $this->hooks[$hook][] = $callback;
    }

    /**
     * @internal
     * @return void
     */
    protected function _resetCalledHooks(): void
    {
        $this->_hooksCalledInCurrentLoadCall = [];
    }
}
