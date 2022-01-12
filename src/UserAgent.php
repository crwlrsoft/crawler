<?php

namespace Crwlr\Crawler;

class UserAgent
{
    public function __construct(
        public string $botName,
        public ?string $infoUri = null, // Uri where site owners can find information about your crawler.
        public ?string $version = null  // In case you want to communicate infos about different versions of your crawler.
    ) {
    }

    public static function make(string $botName, ?string $crawlerInfoUri = null, ?string $version = null): self
    {
        return new static($botName, $crawlerInfoUri, $version);
    }

    public function __toString()
    {
        $botUserAgent = 'Mozilla/5.0 (compatible; ' . $this->botName;

        if ($this->version) {
            $botUserAgent .= '/' . $this->version;
        }

        if ($this->infoUri) {
            $botUserAgent .= '; +' . $this->infoUri;
        }

        return $botUserAgent . ')';
    }
}
