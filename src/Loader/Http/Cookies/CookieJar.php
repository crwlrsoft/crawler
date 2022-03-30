<?php

namespace Crwlr\Crawler\Loader\Http\Cookies;

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
     * @param string $domain
     * @return Cookie[]
     */
    public function allByDomain(string $domain): array
    {
        if (array_key_exists($domain, $this->jar)) {
            return $this->jar[$domain];
        }

        return [];
    }

    public function flush(): void
    {
        $this->jar = [];
    }

    /**
     * @throws InvalidCookieException
     */
    public function addFrom(string|UriInterface|Url $url, ResponseInterface $response): void
    {
        $cookieHeaders = $response->getHeader('set-cookie');

        if (!empty($cookieHeaders)) {
            $url = !$url instanceof Url ? Url::parse($url) : $url;

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
