<?php

namespace Crwlr\Crawler\Cache;

use Crwlr\Crawler\Cache\Exceptions\MissingZlibExtensionException;
use Crwlr\Crawler\Cache\Exceptions\ReadingCacheFailedException;
use DateInterval;
use Exception;
use Psr\SimpleCache\CacheInterface;
use Psr\SimpleCache\InvalidArgumentException;

class FileCache implements CacheInterface
{
    protected DateInterval|int $ttl = 3600;

    protected bool $useCompression = false;

    public function __construct(
        protected readonly string $basePath,
    ) {}

    public function useCompression(): static
    {
        $this->useCompression = true;

        return $this;
    }

    public function ttl(DateInterval|int $ttl): static
    {
        $this->ttl = $ttl;

        return $this;
    }

    /**
     * @throws MissingZlibExtensionException|ReadingCacheFailedException|Exception|InvalidArgumentException
     */
    public function has(string $key): bool
    {
        if (file_exists($this->basePath . '/' . $key)) {
            $cacheItem = $this->getCacheItem($key);

            if (!$cacheItem->isExpired()) {
                return true;
            }

            $this->delete($key);
        }

        return false;
    }

    /**
     * @throws ReadingCacheFailedException|MissingZlibExtensionException|Exception|InvalidArgumentException
     */
    public function get(string $key, mixed $default = null): mixed
    {
        if (file_exists($this->basePath . '/' . $key)) {
            $cacheItem = $this->getCacheItem($key);

            if (!$cacheItem->isExpired()) {
                return $cacheItem->value();
            }

            $this->delete($key);
        }

        return $default;
    }

    /**
     * @throws MissingZlibExtensionException
     * @throws ReadingCacheFailedException
     */
    protected function getCacheItem(string $key): CacheItem
    {
        $fileContent = $this->getFileContents($key);

        if ($this->useCompression) {
            $fileContent = $this->decode($fileContent);
        }

        $unserialized = unserialize($fileContent);

        if (!$unserialized instanceof CacheItem) {
            $unserialized = new CacheItem($unserialized, $key);
        }

        return $unserialized;
    }

    /**
     * @throws MissingZlibExtensionException
     */
    public function set(string $key, mixed $value, DateInterval|int|null $ttl = null): bool
    {
        if (!$value instanceof CacheItem) {
            $value = new CacheItem($value, $key, $ttl ?? $this->ttl);
        } elseif ($value->key() !== $key) {
            $value = new CacheItem($value->value(), $key, $ttl ?? $value->ttl);
        }

        $content = serialize($value);

        if ($this->useCompression) {
            $content = $this->encode($content);
        }

        return file_put_contents($this->basePath . '/' . $key, $content) !== false;
    }

    public function delete(string $key): bool
    {
        return unlink($this->basePath . '/' . $key);
    }

    /**
     * @throws InvalidArgumentException
     */
    public function clear(): bool
    {
        $allFiles = scandir($this->basePath);

        if (is_array($allFiles)) {
            foreach ($allFiles as $file) {
                if ($file !== '.' && $file !== '..' && $file !== '.gitkeep' && !$this->delete($file)) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * @return iterable<mixed>
     * @throws MissingZlibExtensionException|ReadingCacheFailedException|InvalidArgumentException
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
     * @throws MissingZlibExtensionException
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

    /**
     * @throws ReadingCacheFailedException
     */
    protected function getFileContents(string $key): string
    {
        $fileContent = file_get_contents($this->basePath . '/' . $key);

        if ($fileContent === false) {
            throw new ReadingCacheFailedException('Failed to read cache file.');
        }

        return $fileContent;
    }

    /**
     * @throws MissingZlibExtensionException
     */
    protected function encode(string $content): string
    {
        if (!function_exists('gzencode')) {
            throw new MissingZlibExtensionException(
                "Can't compress response cache data. Compression needs PHP ext-zlib installed."
            );
        }

        $encoded = gzencode($content);

        return $encoded === false ? $content : $encoded;
    }

    /**
     * @throws MissingZlibExtensionException
     */
    protected function decode(string $content): string
    {
        $isEncoded = 0 === mb_strpos($content, "\x1f" . "\x8b" . "\x08", 0, "US-ASCII");

        if (!$isEncoded) {
            return $content;
        }

        if (!function_exists('gzdecode')) {
            throw new MissingZlibExtensionException('FileCache compression needs PHP ext-zlib installed.');
        }

        $decoded = gzdecode($content);

        return $decoded === false ? $content : $decoded;
    }
}
