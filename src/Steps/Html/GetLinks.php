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
        $selector = $this->selector ?? 'a';

        $this->logger?->info(
            $this->selector === null ? 'Select all links in document.' : 'Select links with CSS selector: ' . $selector
        );

        foreach ($input->filter($selector) as $link) {
            if ($link->nodeName !== 'a') {
                $this->logger?->warning('Selector matched <' . $link->nodeName . '> html element. Ignored it.');
                continue;
            }

            $link = new Crawler($link);

            yield $this->baseUri->resolve($link->attr('href') ?? '')->__toString();
        }
    }
}
