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
    private function mapProperties(Dot $dot): array
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
}
