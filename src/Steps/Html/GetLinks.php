<?php

namespace Crwlr\Crawler\Steps\Html;

use Crwlr\Crawler\Steps\Dom\HtmlDocument;
use Exception;
use Generator;

class GetLinks extends GetLink
{
    /**
     * @param HtmlDocument $input
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

        foreach ($input->querySelectorAll($selector->query) as $link) {
            $linkUrl = $this->getLinkUrl($link);

            if ($linkUrl) {
                yield (string) $linkUrl;
            }
        }
    }
}
