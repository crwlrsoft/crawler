<?php

namespace Crwlr\Crawler\Loader\Http\Cookies;

use Crwlr\Crawler\Loader\Http\Cookies\Exceptions\InvalidCookieException;
use Crwlr\Url\Url;
use Exception;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;

class CookieJar
{
    /**
     * @var Cookie[][]
     */
    protected array $jar = [];

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
     * @throws Exception
     */
    public function addFrom(string|UriInterface|Url $url, ResponseInterface $response): void
    {
        $cookieHeaders = $response->getHeader('set-cookie');

        if (!empty($cookieHeaders)) {
            $url = !$url instanceof Url ? Url::parse($url) : $url;

            foreach ($cookieHeaders as $cookieHeader) {
                $cookie = new Cookie($url, $cookieHeader);

                $this->jar[$this->getForDomainFromUrl($url)][$cookie->name()] = $cookie;
            }
        }
    }

    /**
     * @return Cookie[]
     * @throws Exception
     */
    public function getFor(string|UriInterface $url): array
    {
        $forDomain = $this->getForDomainFromUrl($url);

        if (!$forDomain || !array_key_exists($forDomain, $this->jar)) {
            return [];
        }

        $cookiesToSend = [];

        foreach ($this->jar[$forDomain] as $cookie) {
            if ($cookie->shouldBeSentTo($url)) {
                $cookiesToSend[] = $cookie;
            }
        }

        return $cookiesToSend;
    }

    protected function getForDomainFromUrl(string|UriInterface|Url $url): ?string
    {
        if (!$url instanceof Url) {
            $url = Url::parse($url);
        }

        $forDomain = empty($url->domain()) ? $url->host() : $url->domain();

        if (!is_string($forDomain)) {
            return null;
        }

        return $forDomain;
    }
}
