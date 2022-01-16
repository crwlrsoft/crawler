<?php

namespace Crwlr\Crawler\Cache;

use Psr\SimpleCache\CacheInterface;

class FileCache implements CacheInterface
{
    public function __construct(private string $basePath)
    {
    }

    public function get(string $key, mixed $default = null): mixed
    {
        if (file_exists($this->basePath . '/' . $key)) {
            return unserialize(file_get_contents($this->basePath . '/' . $key));
        }

        return $default;
    }

    public function set(string $key, mixed $value, \DateInterval|int|null $ttl = null): bool
    {
        return file_put_contents($this->basePath . '/' . $key, serialize($value)) !== false;
    }

    public function delete(string $key): bool
    {
        return unlink($this->basePath . '/' . $key);
    }

    public function clear(): bool
    {
        // TODO: Implement clear() method.
        return true;
    }

    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        return [];
    }

    public function setMultiple(iterable $values, \DateInterval|int|null $ttl = null): bool
    {
        return true;
    }

    public function deleteMultiple(iterable $keys): bool
    {
        return true;
    }

    public function has(string $key): bool
    {
        return file_exists($this->basePath . '/' . $key);
    }
}
