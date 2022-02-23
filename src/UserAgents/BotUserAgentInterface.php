<?php

namespace Crwlr\Crawler\UserAgents;

interface BotUserAgentInterface extends UserAgentInterface
{
    public function productToken(): string;
}
