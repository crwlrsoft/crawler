<?php

namespace Crwlr\Crawler\Http\Cookies;

use Crwlr\Crawler\Exceptions\InvalidCookieException;
use Crwlr\Url\Psr\Uri;
use Crwlr\Url\Url;
use Psr\Http\Message\UriInterface;

class Cookie
{
    private Url $receivedFromUrl;
    private string $receivedFromHost;
    private string $cookieName;
    private string $cookieValue;
    private ?Date $expires = null;
    private ?int $maxAge = null;
    private int $receivedAtTimestamp = 0;
    private string $domain;
    private bool $domainSetViaAttribute = false;
    private ?string $path = null;
    private bool $secure = false;
    private bool $httpOnly = false;
    private string $sameSite = 'Lax';

    /**
     * @throws InvalidCookieException
     */
    public function __construct(
        string|Url $receivedFromUrl,
        private string $setCookieHeader,
    ) {
        $this->receivedFromUrl = $receivedFromUrl instanceof Url ? $receivedFromUrl : Url::parse($receivedFromUrl);

        if (
            !is_string($this->receivedFromUrl->host()) ||
            empty($this->receivedFromUrl->host()) ||
            !is_string($this->receivedFromUrl->domain()) ||
            empty($this->receivedFromUrl->domain())
        ) {
            throw new InvalidCookieException('Url where cookie was received from has no host or domain');
        }

        $this->receivedFromHost = $this->receivedFromUrl->host();
        $this->setDomain($this->receivedFromUrl->domain());
        $this->parseSetCookieHeader($this->setCookieHeader);
    }

    public function shouldBeSentTo(string|UriInterface|Url $url): bool
    {
        $url = $url instanceof Url ? $url : Url::parse($url);
        $urlHost = $url->host() ?? '';

        if (
            !str_contains($urlHost, $this->domain()) ||
            ($this->hasHostPrefix() && $urlHost !== $this->receivedFromHost) ||
            ($this->secure() && $url->scheme() !== 'https' && !in_array($urlHost, ['localhost', '127.0.0.1'], true)) ||
            ($this->path() && !$this->pathMatches($url)) ||
            $this->isExpired()
        ) {
            return false;
        }

        return true;
    }

    public function __toString(): string
    {
        return $this->name() . '=' . $this->value();
    }

    public function receivedFromUrl(): UriInterface
    {
        return new Uri($this->receivedFromUrl);
    }

    public function name(): string
    {
        return $this->cookieName;
    }

    public function value(): string
    {
        return $this->cookieValue;
    }

    public function expires(): ?Date
    {
        return $this->expires;
    }

    public function maxAge(): ?int
    {
        return $this->maxAge;
    }

    public function isExpired(): bool
    {
        if (!$this->expires() && !$this->maxAge()) {
            return false;
        }

        $nowTimestamp = time();

        if ($this->expires() instanceof Date && $nowTimestamp >= $this->expires()->dateTime()->getTimestamp()) {
            return true;
        }

        if ($this->maxAge() > 0 && $nowTimestamp > ($this->receivedAtTimestamp + $this->maxAge())) {
            return true;
        }

        return false;
    }

    public function domain(): string
    {
        return $this->domain;
    }

    public function path(): ?string
    {
        return $this->path;
    }

    public function secure(): bool
    {
        return $this->secure;
    }

    public function httpOnly(): bool
    {
        return $this->httpOnly;
    }

    public function sameSite(): string
    {
        return $this->sameSite;
    }

    public function isReceivedSecure(): bool
    {
        return $this->receivedFromUrl->scheme() === 'https';
    }

    public function hasSecurePrefix(): bool
    {
        return str_starts_with($this->cookieName, '__Secure-');
    }

    public function hasHostPrefix(): bool
    {
        return str_starts_with($this->cookieName, '__Host-');
    }

    private function parseSetCookieHeader(string $setCookieHeader): void
    {
        $splitAtSemicolon = explode(';', $setCookieHeader);
        $splitFirstPart = explode('=', trim(array_shift($splitAtSemicolon)), 2);

        if (count($splitFirstPart) !== 2) {
            throw new InvalidCookieException('Invalid cookie string');
        }

        [$this->cookieName, $this->cookieValue] = $splitFirstPart;

        foreach ($splitAtSemicolon as $attribute) {
            $this->parseAttribute($attribute);
        }

        $this->checkCookiePrefixes();
    }

    private function parseAttribute(string $attribute): void
    {
        $splitAtEquals = explode('=', trim($attribute), 2);
        $attributeName = strtolower($splitAtEquals[0]);
        $attributeValue = $splitAtEquals[1] ?? '';

        if ($attributeName === 'expires') {
            $this->setExpires($attributeValue);
        } elseif ($attributeName === 'max-age') {
            $this->setMaxAge($attributeValue);
        } elseif ($attributeName === 'domain') {
            $this->setDomain($attributeValue, true);
        } elseif ($attributeName === 'path') {
            $this->setPath($attributeValue);
        } elseif ($attributeName === 'secure') {
            $this->setSecure();
        } elseif ($attributeName === 'httponly') {
            $this->httpOnly = true;
        } elseif ($attributeName === 'samesite') {
            $this->setSameSite($attributeValue);
        }
    }

    /**
     * @see https://datatracker.ietf.org/doc/html/draft-west-cookie-prefixes#section-3
     * @throws InvalidCookieException
     */
    private function checkCookiePrefixes(): void
    {
        if ($this->hasSecurePrefix() || $this->hasHostPrefix()) {
            if (!$this->isReceivedSecure()) {
                throw new InvalidCookieException('Cookie is prefixed with __Secure- but was not sent via https');
            }

            if (!$this->secure()) {
                throw new InvalidCookieException('Cookie starts with __Secure- prefix but Secure flag was not sent');
            }
        }

        if ($this->hasHostPrefix()) {
            if ($this->domainSetViaAttribute) {
                throw new InvalidCookieException('Cookie with __Host- prefix must not contain a Domain attribute');
            }

            if ($this->path !== '/') {
                throw new InvalidCookieException('Cookie with __Host- prefix must have a Path attribute with value /');
            }
        }
    }

    private function setExpires(string $value): void
    {
        $this->expires = new Date($value);
    }

    private function setMaxAge(string $value): void
    {
        $this->maxAge = (int) $value;
        $this->receivedAtTimestamp = time();
    }

    /**
     * @throws InvalidCookieException
     */
    private function setDomain(string $value, bool $viaAttribute = false): void
    {
        if (str_starts_with($value, '.')) {
            $value = substr($value, 1);
        }

        if (!str_contains($this->receivedFromHost, $value)) {
            throw new InvalidCookieException(
                'Setting cookie for ' . $value . ' from ' . $this->receivedFromUrl->host() . ' is not allowed.'
            );
        }

        $this->domain = $value;

        if ($viaAttribute) {
            $this->domainSetViaAttribute = true;
        }
    }

    private function setPath(string $path): void
    {
        $this->path = $path;
    }

    private function setSecure(): void
    {
        if (!$this->isReceivedSecure()) {
            throw new InvalidCookieException(
                'Secure flag can\'t be set when cookie was sent from non-https document url.'
            );
        }

        $this->secure = true;
    }

    /**
     * @throws InvalidCookieException
     */
    private function setSameSite(string $value): void
    {
        $value = strtolower($value);

        if (!in_array(strtolower($value), ['strict', 'lax', 'none'], true)) {
            throw new InvalidCookieException('Invalid value for attribute SameSite');
        }

        $this->sameSite = ucfirst($value);
    }

    private function pathMatches(Url $url): bool
    {
        $path = $this->path() ?? '';
        $urlPath = $url->path() ?? '';

        return str_starts_with($urlPath, $path) &&
            (
                $urlPath === $path ||
                $path === '/' ||
                str_starts_with($urlPath, $path . '/')
            );
    }
}
