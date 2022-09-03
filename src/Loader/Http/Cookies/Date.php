<?php

namespace Crwlr\Crawler\Loader\Http\Cookies;

use DateTime;
use DateTimeInterface;
use InvalidArgumentException;

class Date
{
    private ?DateTime $dateTime = null;

    public function __construct(private readonly string $httpDateString)
    {
    }

    /**
     * @throws InvalidArgumentException
     */
    public function dateTime(): DateTime
    {
        if (!$this->dateTime instanceof DateTime) {
            $dateTime = DateTime::createFromFormat(DateTimeInterface::COOKIE, $this->httpDateString);

            if (!$dateTime instanceof DateTime) {
                $dateTime = DateTime::createFromFormat('l, d M Y H:i:s T', $this->httpDateString);

                if (!$dateTime instanceof DateTime) {
                    throw new InvalidArgumentException('Can\'t parse date string ' . $this->httpDateString);
                }
            }

            $this->dateTime = $dateTime;
        }

        return $this->dateTime;
    }
}
