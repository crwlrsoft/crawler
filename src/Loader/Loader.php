<?php

namespace Crwlr\Crawler\Loader;

use Crwlr\Crawler\UserAgent;
use Psr\Log\LoggerInterface;

abstract class Loader implements LoaderInterface
{
    /**
     * This user agent should be set for every request sent by this loader.
     */
    protected UserAgent $userAgent;

    protected LoggerInterface $logger;

    protected array $hooks = [
        'beforeLoad' => null,
        'onSuccess' => null,
        'onError' => null,
        'afterLoad' => null,
    ];

    public function __construct(UserAgent $userAgent, LoggerInterface $logger)
    {
        $this->userAgent = $userAgent;
        $this->logger = $logger;
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

    protected function callHook(string $hook, ...$arguments): void
    {
        $arguments[] = $this->logger;

        if (array_key_exists($hook, $this->hooks) && is_array($this->hooks[$hook])) {
            foreach ($this->hooks[$hook] as $callback) {
                call_user_func($callback, ...$arguments);
            }
        }
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
