<?php

namespace Crwlr\Crawler\Loader\Http\Cache;

use Crwlr\Crawler\Loader\Http\Cache\Exceptions\InvalidArgumentException;
use Crwlr\Crawler\Loader\Http\Cache\Exceptions\MissingZlibExtensionException;
use Crwlr\Crawler\Loader\Http\Cache\Exceptions\ReadingCacheFailedException;
use DateInterval;
use Exception;
use Psr\SimpleCache\CacheInterface;

class FileCache implements CacheInterface
{
    protected bool $useCompression = false;

    public function __construct(
        protected readonly string $basePath,
    ) {
    }

    public function useCompression(): static
    {
        $this->useCompression = true;

        return $this;
    }

    public function has(string $key): bool
    {
        return file_exists($this->basePath . '/' . $key);
    }

    /**
     * @throws ReadingCacheFailedException
     * @throws MissingZlibExtensionException
     * @throws Exception
     */
    public function get(string $key, mixed $default = null): mixed
    {
        if ($this->has($key)) {
            $fileContent = $this->getFileContents($key);

            if ($this->useCompression) {
                $this->decode($fileContent);
            }

            return HttpResponseCacheItem::fromSerialized($fileContent);
        }

        return $default;
    }

    /**
     * @throws InvalidArgumentException
     * @throws MissingZlibExtensionException
     */
    public function set(string $key, mixed $value, DateInterval|int|null $ttl = null): bool
    {
        if (!$value instanceof HttpResponseCacheItem) {
            throw new InvalidArgumentException('This cache stores only HttpResponseCacheItem objects.');
        }

        $content = $value->serialize();

        if ($this->useCompression) {
            $content = $this->encode($content);
        }

        return file_put_contents($this->basePath . '/' . $key, $content) !== false;
    }

    public function delete(string $key): bool
    {
        return unlink($this->basePath . '/' . $key);
    }

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
     * @throws MissingZlibExtensionException
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
     * @throws InvalidArgumentException
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
        if (!function_exists('gzdecode')) {
            throw new MissingZlibExtensionException('FileCache compression needs PHP ext-zlib installed.');
        }

        $decoded = gzdecode($content);

        return $decoded === false ? $content : $decoded;
    }
}
