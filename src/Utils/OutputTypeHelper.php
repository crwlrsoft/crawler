<?php

namespace Crwlr\Crawler\Utils;

class OutputTypeHelper
{
    /**
     * @return mixed[]
     */
    public static function objectToArray(object $output): array
    {
        if (method_exists($output, 'toArrayForResult')) {
            return $output->toArrayForResult();
        } elseif (method_exists($output, 'toArray')) {
            return $output->toArray();
        } elseif (method_exists($output, '__serialize')) {
            return $output->__serialize();
        }

        return (array) $output;
    }

    public static function isScalar(mixed $output): bool
    {
        return !self::isAssociativeArrayOrObject($output);
    }

    public static function isAssociativeArrayOrObject(mixed $output): bool
    {
        return self::isAssociativeArray($output) || is_object($output);
    }

    public static function isAssociativeArray(mixed $output): bool
    {
        if (!is_array($output)) {
            return false;
        }

        foreach ($output as $key => $value) {
            return is_string($key);
        }

        return false;
    }

    /**
     * @param mixed[] $data
     * @return mixed[]
     */
    public static function recursiveChildObjectsToArray(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_object($value)) {
                $data[$key] = self::recursiveChildObjectsToArray(self::objectToArray($value));
            } elseif (is_array($value)) {
                $data[$key] = self::recursiveChildObjectsToArray($value);
            }
        }

        return $data;
    }
}
