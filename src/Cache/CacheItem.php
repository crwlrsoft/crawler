<?php

namespace Crwlr\Crawler\Cache;

use DateInterval;
use DateTimeImmutable;

class CacheItem
{
    protected string $key;

    public function __construct(
        protected mixed $value,
        ?string $key = null,
        public readonly int|DateInterval $ttl = 3600,
        public readonly DateTimeImmutable $createdAt = new DateTimeImmutable(),
    ) {
        if (!$key) {
            if (is_object($this->value) && method_exists($this->value, 'cacheKey')) {
                $this->key = $this->value->cacheKey();
            } else {
                $this->key = md5(serialize($this->value));
            }
        } else {
            $this->key = $key;
        }
    }

    public function key(): string
    {
        return $this->key;
    }

    public function value(): mixed
    {
        return $this->value;
    }

    public function isExpired(): bool
    {
        $ttl = $this->ttl instanceof DateInterval ? $this->ttl : new DateInterval('PT' . $this->ttl . 'S');

        return time() > $this->createdAt->add($ttl)->getTimestamp();
    }

    /**
     * @return mixed[]
     */
    public function __serialize(): array
    {
        return [
            'value' => $this->value,
            'key' => $this->key,
            'ttl' => $this->ttl,
            'createdAt' => $this->createdAt,
        ];
    }

    /**
     * @param mixed[] $data
     */
    public function __unserialize(array $data): void
    {
        $this->value = $data['value'];

        $this->key = $data['key'];

        $this->ttl = $data['ttl'];

        $this->createdAt = $data['createdAt'];
    }
}
