<?php

namespace Crwlr\Crawler\Steps\Sitemap;

use Crwlr\Crawler\Steps\Step;
use Crwlr\Crawler\Steps\StepOutputType;
use Generator;
use Symfony\Component\DomCrawler\Crawler;

class GetUrlsFromSitemap extends Step
{
    protected bool $withData = false;

    /**
     * Remove attributes from a sitemap's <urlset> tag
     *
     * Symfony's DomCrawler component has problems when a sitemap's <urlset> tag contains certain attributes.
     * So, if the count of urls in the sitemap is zero, try to remove all attributes from the <urlset> tag.
     */
    public static function fixUrlSetTag(Crawler $dom): Crawler
    {
        if ($dom->filter('urlset url')->count() === 0) {
            return new Crawler(preg_replace('/<urlset.+?>/', '<urlset>', $dom->outerHtml()));
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
     * @param Crawler $input
     */
    protected function invoke(mixed $input): Generator
    {
        $input = self::fixUrlSetTag($input);

        foreach ($input->filter('urlset url') as $urlNode) {
            $urlNode = new Crawler($urlNode);

            if ($urlNode->children('loc')->first()->count() > 0) {
                if ($this->withData) {
                    yield $this->getWithAdditionalData($urlNode);
                } else {
                    yield $urlNode->children('loc')->first()->text();
                }
            }
        }
    }

    protected function validateAndSanitizeInput(mixed $input): mixed
    {
        return $this->validateAndSanitizeToDomCrawlerInstance($input);
    }

    /**
     * @return string[]
     */
    protected function getWithAdditionalData(Crawler $urlNode): array
    {
        $data = ['url' => $urlNode->children('loc')->first()->text()];

        $properties = ['lastmod', 'changefreq', 'priority'];

        foreach ($properties as $property) {
            $node = $urlNode->children($property)->first();

            if ($node->count() > 0) {
                $data[$property] = $node->text();
            }
        }

        return $data;
    }
}
