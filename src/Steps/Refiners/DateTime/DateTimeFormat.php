<?php

namespace Crwlr\Crawler\Steps\Refiners\DateTime;

use Crwlr\Crawler\Steps\Refiners\String\AbstractStringRefiner;
use DateTime;

class DateTimeFormat extends AbstractStringRefiner
{
    public function __construct(protected string $targetFormat, protected ?string $originFormat = null) {}

    public function refine(mixed $value): mixed
    {
        return $this->apply($value, function ($value) {
            if ($this->originFormat) {
                $parsed = DateTime::createFromFormat($this->originFormat, $value);
            } else {
                $parsed = $this->parseFromUnknownFormat($value);
            }

            if ($parsed === null) {
                return $value;
            } elseif ($parsed === false) {
                $this->logger?->warning(
                    'Failed parsing date/time "' . $value . '", so can\'t reformat it to requested format.',
                );

                return $value;
            }

            return $parsed->format($this->targetFormat);
        }, 'DateTimeRefiner::reformat()');
    }

    private function parseFromUnknownFormat(string $value): ?DateTime
    {
        $timestamp = strtotime($value);

        if ($timestamp === false || $timestamp === 0) {
            $this->logger?->warning(
                'Failed to automatically (without known format) parse date/time "' . $value . '", so can\'t reformat ' .
                'it to requested format.',
            );

            return null;
        }

        return (new DateTime())->setTimestamp($timestamp);
    }
}
