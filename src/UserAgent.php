<?php

namespace Crwlr\Crawler;

class UserAgent
{
    public function __construct(
        public string $productToken,    // The name of the Crawler/Bot
        public ?string $infoUri = null, // Uri where site owners can find information about your crawler.
        public ?string $version = null  // In case you want to communicate infos about different versions of your crawler.
    ) {
    }

    public static function make(string $productToken, ?string $crawlerInfoUri = null, ?string $version = null): self
    {
        return new static($productToken, $crawlerInfoUri, $version);
    }

    public function __toString()
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
}
