<?php

namespace Crwlr\Crawler\Utils;

use Crwlr\Crawler\Cache\Exceptions\MissingZlibExtensionException;

class Gzip
{
    /**
     * @throws MissingZlibExtensionException
     */
    public static function encode(string $string, bool $throwException = false): string
    {
        if (!function_exists('gzencode') && $throwException) {
            throw new MissingZlibExtensionException('PHP ext-zlib not installed.');
        }

        $encoded = gzencode($string);

        return $encoded !== false ? $encoded : $string;
    }

    /**
     * @throws MissingZlibExtensionException
     */
    public static function decode(string $string, bool $throwException = false): string
    {
        $isEncoded = 0 === mb_strpos($string, "\x1f" . "\x8b" . "\x08", 0, "US-ASCII");

        $functionExists = function_exists('gzdecode');

        if (!$isEncoded || !$functionExists) {
            if (!$functionExists && $throwException) {
                throw new MissingZlibExtensionException('PHP ext-zlib not installed.');
            }

            return $string;
        }

        $decoded = gzdecode($string);

        return $decoded !== false ? $decoded : $string;
    }
}
