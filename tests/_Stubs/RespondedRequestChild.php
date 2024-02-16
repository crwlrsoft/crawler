<?php

namespace tests\_Stubs;

use Crwlr\Crawler\Loader\Http\Messages\RespondedRequest;
use Exception;

class RespondedRequestChild extends RespondedRequest
{
    /**
     * @throws Exception
     */
    public static function fromRespondedRequest(RespondedRequest $respondedRequest): self
    {
        return new self($respondedRequest->request, $respondedRequest->response);
    }

    public static function fromArray(array $data): RespondedRequestChild
    {
        $respondedRequest = parent::fromArray($data);

        return self::fromRespondedRequest($respondedRequest);
    }

    public function itseme(): string
    {
        return 'mario';
    }
}
