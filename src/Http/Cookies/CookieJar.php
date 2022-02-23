<?php

namespace Crwlr\Crawler\Http\Cookies;

use Crwlr\Crawler\Exceptions\InvalidCookieException;
use Crwlr\Url\Url;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;

class CookieJar
{
    /**
     * @var Cookie[][]
     */
    private array $jar = [];

    /**
     * @throws InvalidCookieException
     */
    public function addFrom(string|UriInterface $url, ResponseInterface $response): void
    {
        $cookieHeaders = $response->getHeader('set-cookie');

        if (!empty($cookieHeaders)) {
            $url = Url::parse($url);

            foreach ($cookieHeaders as $cookieHeader) {
                $cookie = new Cookie($url, $cookieHeader);
                $this->jar[$url->domain()][$cookie->name()] = $cookie;
            }
        }
    }

    /**
     * @return Cookie[]
     */
    public function getFor(string|UriInterface $url): array
    {
        $url = Url::parse($url);

        if (!is_string($url->domain()) || !array_key_exists($url->domain(), $this->jar)) {
            return [];
        }

        $cookiesToSend = [];

        foreach ($this->jar[$url->domain()] as $cookie) {
            if ($cookie->shouldBeSentTo($url)) {
                $cookiesToSend[] = $cookie;
            }
        }

        return $cookiesToSend;
    }
}
