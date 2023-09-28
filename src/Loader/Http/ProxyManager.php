<?php

namespace Crwlr\Crawler\Loader\Http;

class ProxyManager
{
    protected ?int $lastUsedProxy = null;

    /**
     * @param string[] $proxies
     */
    public function __construct(protected array $proxies)
    {
        $this->proxies = array_values($this->proxies);
    }

    public function singleProxy(): bool
    {
        return count($this->proxies) === 1;
    }

    public function hasOnlySingleProxy(): bool
    {
        return count($this->proxies) === 1;
    }

    public function hasMultipleProxies(): bool
    {
        return count($this->proxies) > 1;
    }

    public function getProxy(): string
    {
        if ($this->hasOnlySingleProxy()) {
            return $this->proxies[0];
        }

        if ($this->lastUsedProxy === null || !isset($this->proxies[$this->lastUsedProxy + 1])) {
            $this->lastUsedProxy = 0;
        } else {
            $this->lastUsedProxy += 1;
        }

        return $this->proxies[$this->lastUsedProxy];
    }
}
