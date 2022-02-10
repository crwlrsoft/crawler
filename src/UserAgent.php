<?php

namespace Crwlr\Crawler;

class UserAgent
{
    /**
     * @param string $productToken  The name of the Crawler/Bot
     * @param string|null $infoUri  Uri where site owners can find information about your crawler.
     * @param string|null $version  In case you want to communicate infos about different versions of your crawler.
     */
    final public function __construct(
        public string $productToken,
        public ?string $infoUri = null,
        public ?string $version = null
    ) {
    }

    public static function make(string $productToken, ?string $crawlerInfoUri = null, ?string $version = null): static
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
