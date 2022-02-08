<?php

namespace Crwlr\Crawler\Cache;

use DateInterval;
use InvalidArgumentException;
use Psr\SimpleCache\CacheInterface;

class FileCache implements CacheInterface
{
    public function __construct(private string $basePath)
    {
    }

    public function has(string $key): bool
    {
        return file_exists($this->basePath . '/' . $key);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        if ($this->has($key)) {
            return HttpResponseCacheItem::fromSerialized(file_get_contents($this->basePath . '/' . $key));
        }

        return $default;
    }

    public function set(string $key, mixed $value, DateInterval|int|null $ttl = null): bool
    {
        if (!$value instanceof HttpResponseCacheItem) {
            throw new InvalidArgumentException('This cache stores only HttpResponseCacheItem objects.');
        }

        return file_put_contents($this->basePath . '/' . $key, $value->serialize()) !== false;
    }

    public function delete(string $key): bool
    {
        return unlink($this->basePath . '/' . $key);
    }

    public function clear(): bool
    {
        foreach (scandir($this->basePath) as $file) {
            if ($file !== '.' && $file !== '..') {
                if (!$this->delete($file)) {
                    return false;
                }
            }
        }

        return true;
    }

    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        $items = [];

        foreach ($keys as $key) {
            $items[$key] = $this->get($key, $default);
        }

        return $items;
    }

    public function setMultiple(iterable $values, DateInterval|int|null $ttl = null): bool
    {
        foreach ($values as $key => $value) {
            if (!$this->set($key, $value, $ttl)) {
                return false;
            }
        }

        return true;
    }

    public function deleteMultiple(iterable $keys): bool
    {
        foreach ($keys as $key) {
            if (!$this->delete($key)) {
                return false;
            }
        }

        return true;
    }
}
