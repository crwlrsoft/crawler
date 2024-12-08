<?php

namespace Crwlr\Crawler\Steps\Sitemap;

use Crwlr\Crawler\Cache\Exceptions\MissingZlibExtensionException;
use Crwlr\Crawler\Steps\Dom\XmlDocument;
use Crwlr\Crawler\Steps\Dom\XmlElement;
use Crwlr\Crawler\Steps\Step;
use Crwlr\Crawler\Steps\StepOutputType;
use Crwlr\Utils\PhpVersion;
use Generator;

class GetUrlsFromSitemap extends Step
{
    protected bool $withData = false;

    /**
     * Remove attributes from a sitemap's <urlset> tag
     *
     * Symfony's DomCrawler component has problems when a sitemap's <urlset> tag contains certain attributes.
     * So, if the count of urls in the sitemap is zero, try to remove all attributes from the <urlset> tag.
     */
    public static function fixUrlSetTag(XmlDocument $dom): XmlDocument
    {
        if ($dom->querySelectorAll('urlset url')->count() === 0) {
            return new XmlDocument(preg_replace('/<urlset.+?>/', '<urlset>', $dom->outerXml()) ?? $dom->outerXml());
        }

        return $dom;
    }

    public function withData(): static
    {
        $this->withData = true;

        return $this;
    }

    public function outputType(): StepOutputType
    {
        return $this->withData ? StepOutputType::AssociativeArrayOrObject : StepOutputType::Scalar;
    }

    /**
     * @param XmlDocument $input
     */
    protected function invoke(mixed $input): Generator
    {
        if (PhpVersion::isBelow(8, 4)) {
            $input = self::fixUrlSetTag($input);
        }

        foreach ($input->querySelectorAll('urlset url') as $urlNode) {
            if ($urlNode->querySelector('loc')) {
                if ($this->withData) {
                    yield $this->getWithAdditionalData($urlNode);
                } else {
                    yield $urlNode->querySelector('loc')->text();
                }
            }
        }
    }

    /**
     * @throws MissingZlibExtensionException
     */
    protected function validateAndSanitizeInput(mixed $input): mixed
    {
        return $this->validateAndSanitizeToXmlDocumentInstance($input);
    }

    /**
     * @return string[]
     */
    protected function getWithAdditionalData(XmlElement $urlNode): array
    {
        $data = ['url' => $urlNode->querySelector('loc')?->text() ?? ''];

        $properties = ['lastmod', 'changefreq', 'priority'];

        foreach ($properties as $property) {
            $node = $urlNode->querySelector($property);

            if ($node) {
                $data[$property] = $node->text();
            }
        }

        return $data;
    }
}
