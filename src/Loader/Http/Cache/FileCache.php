<?php

namespace Crwlr\Crawler\Loader\Http\Cache;

use Crwlr\Crawler\Loader\Http\Cache\Exceptions\InvalidArgumentException;
use Crwlr\Crawler\Loader\Http\Cache\Exceptions\ReadingCacheFailedException;
use DateInterval;
use Exception;
use Psr\SimpleCache\CacheInterface;

class FileCache implements CacheInterface
{
    public function __construct(private readonly string $basePath)
    {
    }

    public function has(string $key): bool
    {
        return file_exists($this->basePath . '/' . $key);
    }

    /**
     * @throws ReadingCacheFailedException
     * @throws Exception
     */
    public function get(string $key, mixed $default = null): mixed
    {
        if ($this->has($key)) {
            $fileContent = file_get_contents($this->basePath . '/' . $key);

            if ($fileContent === false) {
                throw new ReadingCacheFailedException('Failed to read file ' . $this->basePath . '/' . $key);
            }

            return HttpResponseCacheItem::fromSerialized($fileContent);
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

    /**
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function clear(): bool
    {
        $allFiles = scandir($this->basePath);

        if (is_array($allFiles)) {
            foreach ($allFiles as $file) {
                if ($file !== '.' && $file !== '..' && !$this->delete($file)) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * @return iterable<mixed>
     * @throws ReadingCacheFailedException
     */
    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        $items = [];

        foreach ($keys as $key) {
            $items[$key] = $this->get($key, $default);
        }

        return $items;
    }

    /**
     * @param iterable<mixed> $values
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
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
