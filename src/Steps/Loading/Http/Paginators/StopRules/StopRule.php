<?php

namespace Crwlr\Crawler\Steps\Loading\Http\Paginators\StopRules;

use Crwlr\Crawler\Loader\Http\Messages\RespondedRequest;
use Psr\Http\Message\RequestInterface;

interface StopRule
{
    public function shouldStop(RequestInterface $request, ?RespondedRequest $respondedRequest): bool;
}
