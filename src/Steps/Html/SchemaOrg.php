<?php

namespace Crwlr\Crawler\Steps\Html;

use Adbar\Dot;
use Crwlr\Crawler\Steps\Step;
use Generator;
use Spatie\SchemaOrg\BaseType;

class SchemaOrg extends Step
{
    protected bool $toArray = false;
    protected ?string $onlyType = null;

    /**
     * @var array<int|string, string>
     */
    protected array $mapping = [];

    public function toArray(): static
    {
        $this->toArray = true;

        return $this;
    }

    public function onlyType(string $type = ''): static
    {
        $this->onlyType = $type;

        return $this;
    }

    /**
     * @param array<int|string, string> $mapping
     */
    public function extract(array $mapping): static
    {
        $this->mapping = $mapping;

        return $this;
    }

    protected function invoke(mixed $input): Generator
    {
        $data = \Crwlr\SchemaOrg\SchemaOrg::fromHtml($input);

        foreach ($data as $schemaOrgObject) {
            if ($this->onlyType && $schemaOrgObject->getType() !== $this->onlyType) {
                yield from $this->scanChildrenForType($schemaOrgObject);

                continue;
            }

            yield $this->prepareReturnValue($schemaOrgObject);
        }
    }

    protected function validateAndSanitizeInput(mixed $input): string
    {
        return $this->validateAndSanitizeStringOrHttpResponse($input);
    }

    protected function scanChildrenForType(BaseType $schemaOrgObject): Generator
    {
        foreach ($schemaOrgObject->getProperties() as $propertyName => $property) {
            $propertyValue = $schemaOrgObject->getProperty($propertyName);

            if ($propertyValue instanceof BaseType && $propertyValue->getType() === $this->onlyType) {
                yield $this->prepareReturnValue($propertyValue);
            } elseif ($propertyValue instanceof BaseType) {
                yield from $this->scanChildrenForType($propertyValue);
            }
        }
    }

    /**
     * @return BaseType|mixed[]
     */
    protected function prepareReturnValue(BaseType $object): BaseType|array
    {
        if ($this->toArray || !empty($this->mapping)) {
            if (empty($this->mapping)) {
                return $object->toArray();
            }

            return $this->applyMapping($object->toArray());
        }

        return $object;
    }

    /**
     * @param mixed[] $schemaOrgData
     * @return mixed[]
     */
    protected function applyMapping(array $schemaOrgData): array
    {
        $extractedData = [];

        $dot = new Dot($schemaOrgData);

        foreach ($this->mapping as $outputKey => $dotNotationKey) {
            if (is_int($outputKey)) {
                $outputKey = $dotNotationKey;
            }

            $extractedData[$outputKey] = $dot->get($dotNotationKey);
        }

        return $extractedData;
    }
}
