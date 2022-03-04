<?php

namespace Crwlr\Crawler\Steps\Html;

use Crwlr\Crawler\Input;
use DOMElement;
use Exception;
use Generator;

class GetLinks extends GetLink
{
    /**
     * @param Input $input
     * @return Generator<string>
     * @throws Exception
     */
    protected function invoke(Input $input): Generator
    {
        $selector = $this->selector ?? 'a';
        $this->logger->info(
            $this->selector === null ? 'Select all links in document.' : 'Select links with CSS selector: ' . $selector
        );

        foreach ($input->get()->filter($selector) as $link) {
            /** @var DOMElement $link */
            if ($link->nodeName !== 'a') {
                $this->logger->warning('Selector matched <' . $link->nodeName . '> html element. Ignored it.');
                continue;
            }

            yield $this->baseUri->resolve($link->getAttribute('href'))->__toString();
        }
    }
}
