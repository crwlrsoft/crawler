<?php

namespace Crwlr\Crawler\Steps\Html;

use Exception;
use Generator;
use Symfony\Component\DomCrawler\Crawler;

class GetLinks extends GetLink
{
    /**
     * @param Crawler $input
     * @return Generator<string>
     * @throws Exception
     */
    protected function invoke(mixed $input): Generator
    {
        $this->getBaseFromDocument($input);

        $selector = $this->selector ?? 'a';

        if (is_string($selector)) {
            $selector = new CssSelector($selector);
        }

        foreach ($selector->filter($input) as $link) {
            $linkUrl = $this->getLinkUrl($link);

            if ($linkUrl) {
                yield (string) $linkUrl;
            }
        }
    }
}
