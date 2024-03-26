<?php

namespace Crwlr\Crawler\Steps\Html;

use Crwlr\Crawler\Steps\Step;
use Crwlr\Crawler\Steps\StepOutputType;
use DOMElement;
use Generator;
use Symfony\Component\DomCrawler\Crawler;

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
     * @param Crawler $input
     */
    protected function invoke(mixed $input): Generator
    {
        $data = $this->addToData([], 'title', $this->getTitle($input));

        foreach ($input->filter('meta') as $metaElement) {
            /** @var DOMElement $metaElement */
            $metaName = $metaElement->getAttribute('name');

            if (empty($metaName)) {
                $metaName = $metaElement->getAttribute('property');
            }

            if (!empty($metaName) && (empty($this->onlyKeys) || in_array($metaName, $this->onlyKeys, true))) {
                $data = $this->addToData($data, $metaName, $metaElement->getAttribute('content'));
            }
        }

        yield $data;
    }

    protected function validateAndSanitizeInput(mixed $input): mixed
    {
        return $this->validateAndSanitizeToDomCrawlerInstance($input);
    }

    protected function getTitle(Crawler $document): string
    {
        $titleElement = $document->filter('title');

        if ($titleElement->count() > 0) {
            return $titleElement->first()->text();
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
