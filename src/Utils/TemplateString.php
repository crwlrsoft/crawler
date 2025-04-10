<?php

namespace Crwlr\Crawler\Utils;

use Adbar\Dot;

class TemplateString
{
    /**
     * @param mixed[] $data
     */
    public static function resolve(string $string, array $data = []): string
    {
        if (str_contains($string, '[crwl:')) {
            return preg_replace_callback('/\[crwl:(.+?)]/m', function ($matches) use ($data) {
                $varName = self::trimAndUnescapeQuotes($matches[1]);

                if (array_key_exists($varName, $data)) {
                    return $data[$varName];
                } elseif (str_contains($varName, '.')) {
                    $dot = new Dot($data);

                    return $dot->get($varName);
                }

                return '';
            }, $string) ?? $string;
        }

        return $string;
    }

    private static function trimAndUnescapeQuotes(string $string): string
    {
        if (
            str_starts_with($string, '\'') && str_ends_with($string, '\'') ||
            str_starts_with($string, '"') && str_ends_with($string, '"')
        ) {
            $string = substr($string, 1, -1);
        }

        $string = str_replace(["\'", '\"'], ["'", '"'], $string);

        return $string;
    }
}
