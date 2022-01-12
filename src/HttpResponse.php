<?php

namespace Crwlr\Crawler;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class HttpResponse
{
    public function __construct(
        public RequestInterface $request,
        public ResponseInterface $response,
    ) {
    }
}
