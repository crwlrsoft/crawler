<?php

namespace Crwlr\Crawler\Steps;

use Adbar\Dot;
use Generator;

class Json extends Step
{
    /**
     * @param mixed[] $propertyMapping
     */
    final public function __construct(protected array $propertyMapping = [], protected ?string $each = null)
    {
    }

    /**
     * @param mixed[] $propertyMapping
     */
    public static function get(array $propertyMapping = []): static
    {
        return new static($propertyMapping);
    }

    /**
     * @param mixed[] $propertyMapping
     */
    public static function each(string $each, array $propertyMapping = []): static
    {
        return new static($propertyMapping, $each);
    }

    protected function validateAndSanitizeInput(mixed $input): mixed
    {
        return $this->validateAndSanitizeStringOrHttpResponse($input);
    }

    protected function invoke(mixed $input): Generator
    {
        $array = json_decode($input, true);

        if ($array === null) {
            $array = json_decode($this->fixJsonString($input), true);

            if ($array === null) {
                $this->logger?->warning('Failed to decode JSON string.');

                return;
            }
        }

        $dot = new Dot($array);

        if ($this->each === null) {
            yield $this->mapProperties($dot);
        } else {
            $each = $this->each === '' ? $dot->get() : $dot->get($this->each);

            foreach ($each as $item) {
                yield $this->mapProperties(new Dot($item));
            }
        }
    }

    /**
     * @param Dot<int|string, mixed> $dot
     * @return mixed[]
     */
    protected function mapProperties(Dot $dot): array
    {
        $mapped = [];

        foreach ($this->propertyMapping as $propertyKey => $dotNotation) {
            if (is_int($propertyKey)) {
                $propertyKey = $dotNotation;
            }

            $mapped[$propertyKey] = $dot->get($dotNotation);
        }

        return $mapped;
    }

    /**
     * Try to fix JSON keys without quotes
     *
     * PHPs json_decode() doesn't work with JSON objects where the keys are not wrapped in quotes.
     * This method tries to fix this, when json_decode() fails to parse a JSON string.
     */
    protected function fixJsonString(string $jsonString): string
    {
        return preg_replace_callback('/(\w+):/i', function ($match) {
            $key = $match[1];

            if (!str_starts_with($key, '"')) {
                $key = '"' . $key;
            }

            if (!str_ends_with($key, '"')) {
                $key = $key . '"';
            }

            return $key . ':';
        }, $jsonString) ?? $jsonString;
    }
}
