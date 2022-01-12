<?php

namespace Crwlr\Crawler;

class MobileUserAgent extends UserAgent
{
    public function __toString()
    {
        $botUserAgent = 'Mozilla/5.0 (iPhone; CPU iPhone OS 15_1 like Mac OS X) AppleWebKit/605.1.15' .
            ' (KHTML, like Gecko) CriOS/96.0.4664.53 Mobile/15E148 Safari/604.1 (compatible; ' . $this->botName;

        if ($this->version) {
            $botUserAgent .= '/' . $this->version;
        }

        if ($this->infoUri) {
            $botUserAgent .= '; +' . $this->infoUri;
        }

        return $botUserAgent . ')';
    }
}
