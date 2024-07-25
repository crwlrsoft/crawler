<?php

namespace Crwlr\Crawler\Cache;

use Crwlr\Crawler\Cache\Exceptions\MissingZlibExtensionException;
use Crwlr\Crawler\Cache\Exceptions\ReadingCacheFailedException;
use Crwlr\Crawler\Utils\Gzip;
use DateInterval;
use Exception;
use Psr\SimpleCache\CacheInterface;
use Psr\SimpleCache\InvalidArgumentException;
use Throwable;

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
     * @throws MissingZlibExtensionException
     * @throws ReadingCacheFailedException
     */
    protected function getCacheItem(string $key): CacheItem
    {
        $fileContent = $this->getFileContents($key);

        if ($this->useCompression) {
            $fileContent = $this->decode($fileContent);
        }

        $unserialized = $this->unserialize($fileContent);

        if (!$unserialized instanceof CacheItem) {
            $unserialized = new CacheItem($unserialized, $key);
        }

        return $unserialized;
    }

    protected function unserialize(string $content): mixed
    {
        // Temporarily set a new error handler, so unserializing a compressed string does not result in a PHP warning.
        $previousHandler = set_error_handler(function ($errno, $errstr) {
            return $errno === E_WARNING && str_starts_with($errstr, 'unserialize(): Error at offset 0 of ');
        });

        $unserialized = unserialize($content);

        if ($unserialized === false) { // if unserializing fails, try if the string is compressed.
            try {
                $content = $this->decode($content);

                $unserialized = unserialize($content);
            } catch (Throwable) {
            }
        }

        set_error_handler($previousHandler);

        return $unserialized;
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
        try {
            return Gzip::encode($content, true);
        } catch (MissingZlibExtensionException) {
            throw new MissingZlibExtensionException(
                'Can\'t compress response cache data. Compression needs PHP ext-zlib installed.',
            );
        }
    }

    /**
     * @throws MissingZlibExtensionException
     */
    protected function decode(string $content): string
    {
        try {
            return Gzip::decode($content, true);
        } catch (MissingZlibExtensionException) {
            throw new MissingZlibExtensionException('FileCache compression needs PHP ext-zlib installed.');
        }
    }
}
