<?php

namespace Crwlr\Crawler\UserAgents;

class BotUserAgent implements BotUserAgentInterface
{
    /**
     * @param string $productToken  The name of the Crawler/Bot
     * @param string|null $infoUri  Uri where site owners can find information about your crawler.
     * @param string|null $version  In case you want to communicate infos about different versions of your crawler.
     */
    public function __construct(
        protected string $productToken,
        protected ?string $infoUri = null,
        protected ?string $version = null
    ) {}

    public static function make(string $productToken, ?string $crawlerInfoUri = null, ?string $version = null): self
    {
        return new self($productToken, $crawlerInfoUri, $version);
    }

    public function __toString(): string
    {
        $botUserAgent = 'Mozilla/5.0 (compatible; ' . $this->productToken;

        if ($this->version) {
            $botUserAgent .= '/' . $this->version;
        }

        if ($this->infoUri) {
            $botUserAgent .= '; +' . $this->infoUri;
        }

        return $botUserAgent . ')';
    }

    public function productToken(): string
    {
        return $this->productToken;
    }
}
