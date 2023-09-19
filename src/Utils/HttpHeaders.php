<?php

namespace Crwlr\Crawler\Utils;

final class HttpHeaders
{
    /**
     * @param array<string, string|string[]> $headers
     * @return array<string, string[]>
     */
    public static function normalize(array $headers): array
    {
        $normalized = [];

        foreach ($headers as $headerName => $value) {
            $normalized[$headerName] = is_array($value) ? $value : [$value];
        }

        return $normalized;
    }

    /**
     * @param array<string, array<int, string>> $headers
     * @param array<string, array<int, string>> $mergeHeaders
     * @return array<string, array<int, string>>
     */
    public static function merge(array $headers, array $mergeHeaders): array
    {
        foreach ($mergeHeaders as $headerName => $value) {
            if (!array_key_exists($headerName, $headers)) {
                $headers[$headerName] = $value;
            } else {
                $headers = self::addTo($headers, $headerName, $value);
            }
        }

        return $headers;
    }

    /**
     * @param array<string, array<int, string>> $headers
     * @param string $headerName
     * @param string|string[] $value
     * @return array<string, array<int, string>>
     */
    public static function addTo(array $headers, string $headerName, string|array $value): array
    {
        if (!array_key_exists($headerName, $headers)) {
            $headers[$headerName] = is_array($value) ? $value : [$value];
        } elseif (is_array($value)) {
            foreach ($value as $valueItem) {
                if (!in_array($valueItem, $headers[$headerName], true)) {
                    $headers[$headerName][] = $valueItem;
                }
            }
        } elseif (!in_array($value, $headers[$headerName], true)) {
            $headers[$headerName][] = $value;
        }

        return $headers;
    }
}
