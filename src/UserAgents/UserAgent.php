<?php

namespace Crwlr\Crawler\UserAgents;

class UserAgent implements UserAgentInterface
{
    public function __construct(protected readonly string $userAgent) {}

    public function __toString(): string
    {
        return $this->userAgent;
    }

    public static function mozilla5CompatibleBrowser(): self
    {
        return new self('Mozilla/5.0 (compatible)');
    }
}
