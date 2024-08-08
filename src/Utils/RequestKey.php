<?php

namespace Crwlr\Crawler\Utils;

use Crwlr\Crawler\Cache\Exceptions\MissingZlibExtensionException;
use Crwlr\Crawler\Loader\Http\Messages\RespondedRequest;
use Crwlr\Crawler\Steps\Loading\Http;
use Psr\Http\Message\RequestInterface;

class RequestKey
{
    /**
     * Creates a unique key for an HTTP request
     *
     * The key will be based on all its properties: method, URI, headers, body.
     * So, for example, if requests send different bodies, but the rest is identical, the keys will be different.
     *
     * By default, Cookie headers are removed before building the key, so the key is independent of sessions.
     * You can also pass other headers (or none if you want cookies to be included) to be ignored as second argument.
     *
     * @param RequestInterface|RespondedRequest $request
     * @param string[] $ignoreHeaders
     * @return string
     * @throws MissingZlibExtensionException
     */
    public static function from(RequestInterface|RespondedRequest $request, array $ignoreHeaders = ['Cookie']): string
    {
        $request = $request instanceof RespondedRequest ? $request->request : $request;

        $data = [
            'requestMethod' => $request->getMethod(),
            'requestUri' => $request->getUri()->__toString(),
            'requestHeaders' => $request->getHeaders(),
            'requestBody' => Http::getBodyString($request),
        ];

        $data = self::removeIgnoreHeaders($data, $ignoreHeaders);

        $serialized = serialize($data);

        return md5($serialized);
    }

    /**
     * @param array<string, mixed> $data
     * @param string[] $ignoreHeaders
     * @return array<string, mixed>
     */
    private static function removeIgnoreHeaders(array $data, array $ignoreHeaders): array
    {
        foreach ($ignoreHeaders as $ignoreHeader) {
            if (isset($data['requestHeaders'][$ignoreHeader])) {
                unset($data['requestHeaders'][$ignoreHeader]);
            }

            $otherCase = strtolower($ignoreHeader);

            if ($otherCase === $ignoreHeader) {
                $otherCase = ucwords($ignoreHeader, '-');
            }

            $ignoreHeader = $otherCase;

            if (isset($data['requestHeaders'][$ignoreHeader])) {
                unset($data['requestHeaders'][$ignoreHeader]);
            }
        }

        return $data;
    }
}
