<?php

namespace Crwlr\Crawler\Loader\Http\Cookies;

use Crwlr\Crawler\Loader\Http\Cookies\Exceptions\InvalidCookieException;
use Crwlr\Url\Url;
use DateTime;
use Exception;
use HeadlessChromium\Cookies\CookiesCollection;
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
    public function addFrom(string|UriInterface|Url $url, ResponseInterface|CookiesCollection $response): void
    {
        if ($response instanceof CookiesCollection) {
            $this->addFromBrowserCookieCollection($url, $response);
        } else {
            $cookieHeaders = $response->getHeader('set-cookie');

            if (!empty($cookieHeaders)) {
                $url = !$url instanceof Url ? Url::parse($url) : $url;

                foreach ($cookieHeaders as $cookieHeader) {
                    $cookie = new Cookie($url, $cookieHeader);

                    $this->jar[$cookie->domain()][$cookie->name()] = $cookie;
                }
            }
        }
    }

    /**
     * @throws InvalidCookieException
     * @throws Exception
     */
    public function addFromBrowserCookieCollection(string|UriInterface|Url $url, CookiesCollection $collection): void
    {
        if ($collection->count() > 0) {
            $url = !$url instanceof Url ? Url::parse($url) : $url;

            foreach ($collection as $cookie) {
                $cookie = new Cookie($url, $this->buildSetCookieHeaderFromBrowserCookie($cookie));

                $this->jar[$cookie->domain()][$cookie->name()] = $cookie;
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

    /**
     * @throws Exception
     */
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

    protected function buildSetCookieHeaderFromBrowserCookie(\HeadlessChromium\Cookies\Cookie $cookie): string
    {
        $header = $cookie->getName() . '=' . $cookie->getValue();

        if ($cookie->getDomain() !== null) {
            $header .= '; Domain=' . $cookie->getDomain();
        }

        if ($cookie->offsetExists('expires') && $cookie->offsetGet('expires') !== -1) {
            $header .= '; Expires=' . $this->formatExpiresValue($cookie->offsetGet('expires'));
        }

        if ($cookie->offsetExists('max-age') && !empty($cookie->offsetGet('path'))) {
            $header .= '; Max-Age=' . $cookie->offsetGet('max-age');
        }

        if ($cookie->offsetExists('path') && !empty($cookie->offsetGet('path'))) {
            $header .= '; Path=' . $cookie->offsetGet('path');
        }

        if ($cookie->offsetExists('secure') && !empty($cookie->offsetGet('secure'))) {
            $header .= '; Secure=' . $cookie->offsetGet('path');
        }

        if ($cookie->offsetExists('httpOnly') && $cookie->offsetGet('httpOnly') === true) {
            $header .= '; HttpOnly';
        }

        if ($cookie->offsetExists('sameSite') && !empty($cookie->offsetGet('sameSite'))) {
            $header .= '; SameSite=' . $cookie->offsetGet('sameSite');
        }

        return $header;
    }

    private function formatExpiresValue(mixed $value): string
    {
        if (is_numeric($value)) {
            $expires = DateTime::createFromFormat('U.v', (string) $value);

            if ($expires !== false) {
                return $expires->format('l, d M Y H:i:s T');
            }
        }

        return (string) $value;
    }
}
