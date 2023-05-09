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

        foreach ($input->filter($selector) as $link) {
            if ($link->nodeName !== 'a') {
                $this->logger?->warning('Selector matched <' . $link->nodeName . '> html element. Ignored it.');
                continue;
            }

            $linkUrl = $this->handleUrlFragment(
                $this->baseUri->resolve((new Crawler($link))->attr('href') ?? '')
            );

            if ($this->matchesAdditionalCriteria($linkUrl)) {
                yield $linkUrl->__toString();
            }
        }
    }
}
