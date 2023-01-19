<?php

namespace Crwlr\Crawler\Loader\Http\Cookies;

use Crwlr\Crawler\Loader\Http\Cookies\Exceptions\InvalidCookieException;
use Crwlr\Url\Psr\Uri;
use Crwlr\Url\Url;
use Exception;
use Psr\Http\Message\UriInterface;

class Cookie
{
    protected Url $receivedFromUrl;

    protected string $receivedFromHost;

    protected string $cookieName;

    protected string $cookieValue;

    protected ?Date $expires = null;

    protected ?int $maxAge = null;

    protected int $receivedAtTimestamp = 0;

    protected string $domain;

    protected bool $domainSetViaAttribute = false;

    protected ?string $path = null;

    protected bool $secure = false;

    protected bool $httpOnly = false;

    protected string $sameSite = 'Lax';

    /**
     * @throws InvalidCookieException
     * @throws Exception
     */
    public function __construct(
        string|Url              $receivedFromUrl,
        protected readonly string $setCookieHeader,
    ) {
        $this->receivedFromUrl = $receivedFromUrl instanceof Url ? $receivedFromUrl : Url::parse($receivedFromUrl);

        if (
            !is_string($this->receivedFromUrl->host()) ||
            empty($this->receivedFromUrl->host())
        ) {
            throw new InvalidCookieException('Url where cookie was received from has no host or domain');
        }

        $this->receivedFromHost = $this->receivedFromUrl->host();

        $this->setDomain($this->receivedFromUrl->domain() ?? $this->receivedFromUrl->host());

        $this->parseSetCookieHeader($this->setCookieHeader);
    }

    /**
     * @throws Exception
     */
    public function shouldBeSentTo(string|UriInterface|Url $url): bool
    {
        $url = $url instanceof Url ? $url : Url::parse($url);

        $urlHost = $url->host() ?? '';

        return
            str_contains($urlHost, $this->domain()) &&
            (!$this->hasHostPrefix() || $urlHost === $this->receivedFromHost) &&
            (!$this->secure() || $url->scheme() === 'https' || in_array($urlHost, ['localhost', '127.0.0.1'], true)) &&
            (!$this->path() || $this->pathMatches($url)) &&
            !$this->isExpired();
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
        if ($this->expires() === null && $this->maxAge() === null) {
            return false;
        }

        $nowTimestamp = time();

        if ($this->expires() instanceof Date && $nowTimestamp >= $this->expires()->dateTime()->getTimestamp()) {
            return true;
        }

        return $this->maxAge() !== null &&
            ($this->maxAge() <= 0 || $nowTimestamp > ($this->receivedAtTimestamp + $this->maxAge()));
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

    /**
     * @throws Exception
     */
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

    /**
     * @throws InvalidCookieException
     */
    protected function parseSetCookieHeader(string $setCookieHeader): void
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

        $this->checkPrefixes();
    }

    /**
     * @throws InvalidCookieException
     */
    protected function parseAttribute(string $attribute): void
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
     * @throws Exception
     */
    protected function checkPrefixes(): void
    {
        if ($this->hasSecurePrefix() || $this->hasHostPrefix()) {
            if (!$this->isReceivedSecure()) {
                throw new InvalidCookieException(
                    'Cookie is prefixed with __Secure- or __Host- but was not sent via https'
                );
            }

            if (!$this->secure()) {
                throw new InvalidCookieException(
                    'Cookie is prefixed with __Secure- or __Host- but Secure flag was not sent'
                );
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

    protected function setExpires(string $value): void
    {
        $this->expires = new Date($value);
    }

    protected function setMaxAge(string $value): void
    {
        $this->maxAge = (int) $value;

        $this->receivedAtTimestamp = time();
    }

    /**
     * @throws InvalidCookieException
     * @throws Exception
     */
    protected function setDomain(string $value, bool $viaAttribute = false): void
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

    protected function setPath(string $path): void
    {
        $this->path = $path;
    }

    /**
     * @throws InvalidCookieException
     * @throws Exception
     */
    protected function setSecure(): void
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
    protected function setSameSite(string $value): void
    {
        $value = strtolower($value);

        if (!in_array(strtolower($value), ['strict', 'lax', 'none'], true)) {
            throw new InvalidCookieException('Invalid value for attribute SameSite');
        }

        $this->sameSite = ucfirst($value);
    }

    /**
     * @throws Exception
     */
    protected function pathMatches(Url $url): bool
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
