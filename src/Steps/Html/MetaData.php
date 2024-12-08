<?php

namespace Crwlr\Crawler\Steps\Html;

use Crwlr\Crawler\Cache\Exceptions\MissingZlibExtensionException;
use Crwlr\Crawler\Steps\Dom\HtmlDocument;
use Crwlr\Crawler\Steps\Step;
use Crwlr\Crawler\Steps\StepOutputType;
use Generator;

class MetaData extends Step
{
    /**
     * @var string[]
     */
    protected array $onlyKeys = [];

    /**
     * @param string[] $keys
     */
    public function only(array $keys): static
    {
        $this->onlyKeys = $keys;

        return $this;
    }

    public function outputType(): StepOutputType
    {
        return StepOutputType::AssociativeArrayOrObject;
    }

    /**
     * @param HtmlDocument $input
     */
    protected function invoke(mixed $input): Generator
    {
        $data = $this->addToData([], 'title', $this->getTitle($input));

        foreach ($input->querySelectorAll('meta') as $metaElement) {
            $metaName = $metaElement->getAttribute('name');

            if (empty($metaName)) {
                $metaName = $metaElement->getAttribute('property');
            }

            if (!empty($metaName) && (empty($this->onlyKeys) || in_array($metaName, $this->onlyKeys, true))) {
                $data = $this->addToData($data, $metaName, $metaElement->getAttribute('content') ?? '');
            }
        }

        yield $data;
    }

    /**
     * @throws MissingZlibExtensionException
     */
    protected function validateAndSanitizeInput(mixed $input): mixed
    {
        return $this->validateAndSanitizeToHtmlDocumentInstance($input);
    }

    protected function getTitle(HtmlDocument $document): string
    {
        $titleElement = $document->querySelector('title');

        if ($titleElement) {
            return $titleElement->text();
        }

        return '';
    }

    /**
     * @param array<string, string> $data
     * @return array<string, string>
     */
    protected function addToData(array $data, string $key, string $value): array
    {
        if (empty($this->onlyKeys) || in_array($key, $this->onlyKeys, true)) {
            $data[$key] = $value;
        }

        return $data;
    }
}
