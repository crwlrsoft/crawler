<?php

namespace Crwlr\Crawler\Steps\Loading\Http\Paginators\StopRules;

use Adbar\Dot;
use Crwlr\Crawler\Loader\Http\Messages\RespondedRequest;
use Crwlr\Crawler\Steps\Loading\Http;
use Crwlr\Utils\Exceptions\InvalidJsonException;
use Crwlr\Utils\Json;
use Psr\Http\Message\RequestInterface;

class IsEmptyInJson implements StopRule
{
    public function __construct(protected string $dotNotationKey) {}

    /**
     * @throws InvalidJsonException
     */
    public function shouldStop(RequestInterface $request, ?RespondedRequest $respondedRequest): bool
    {
        if (!$respondedRequest) {
            return true;
        }

        $content = trim(Http::getBodyString($respondedRequest->response));

        $json = Json::stringToArray($content);

        $dot = new Dot($json);

        return empty($dot->get($this->dotNotationKey));
    }
}
