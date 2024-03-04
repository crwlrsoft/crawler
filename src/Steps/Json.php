<?php

namespace Crwlr\Crawler\Steps;

use Adbar\Dot;
use Crwlr\Utils\Json as JsonUtil;
use Crwlr\Utils\Exceptions\InvalidJsonException;
use Generator;
use Symfony\Component\DomCrawler\Crawler;
use Throwable;

class Json extends Step
{
    /**
     * @param mixed[] $propertyMapping
     */
    final public function __construct(protected ?array $propertyMapping = [], protected ?string $each = null) {}

    public static function all(): static
    {
        return new static(null);
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
        $array = $this->inputStringToArray($input);

        if ($array === null || $this->propertyMapping === null) {
            if ($array === null) {
                $this->logger?->warning('Failed to decode JSON string.');
            } elseif ($this->propertyMapping === null) {
                yield $array;
            }

            return;
        }

        $dot = new Dot($array);

        if ($this->each === null) {
            yield $this->mapProperties($dot);
        } else {
            $each = $this->each === '' ? $dot->get() : $dot->get($this->each);

            if (!is_iterable($each)) {
                $this->logger?->warning('The target of "each" does not exist in the JSON data.');
            } else {
                foreach ($each as $item) {
                    yield $this->mapProperties(new Dot($item));
                }
            }
        }
    }

    /**
     * @return mixed[]|null
     */
    protected function inputStringToArray(string $input): ?array
    {
        try {
            return JsonUtil::stringToArray($input);
        } catch (InvalidJsonException) {
            // If headless browser is used in loader, the JSON in the response body is wrapped in an HTML document.
            if (str_contains($input, '<html') || str_contains($input, '<HTML')) {
                try {
                    $bodyText = (new Crawler($input))->filter('body')->text();

                    return JsonUtil::stringToArray($bodyText);
                } catch (Throwable) {
                }
            }
        }

        return null;
    }

    /**
     * @param Dot<int|string, mixed> $dot
     * @return mixed[]
     */
    protected function mapProperties(Dot $dot): array
    {
        if ($this->propertyMapping === null || $this->propertyMapping === []) {
            return [];
        }

        $mapped = [];

        foreach ($this->propertyMapping as $propertyKey => $dotNotation) {
            if (is_int($propertyKey)) {
                $propertyKey = $dotNotation;
            }

            if ($dotNotation === '' || ($dotNotation === '*' && $dot->get('*') === null)) {
                $mapped[$propertyKey] = $dot->all();
            } else {
                $mapped[$propertyKey] = $dot->get($dotNotation);
            }
        }

        return $mapped;
    }
}
