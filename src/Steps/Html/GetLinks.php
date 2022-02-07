<?php

namespace Crwlr\Crawler\Steps\Html;

use Crwlr\Crawler\Aggregates\RequestResponseAggregate;
use Crwlr\Crawler\Input;
use Crwlr\Crawler\Steps\Step;
use Crwlr\Url\Url;
use DOMElement;
use InvalidArgumentException;
use Symfony\Component\DomCrawler\Crawler;

class GetLinks extends Step
{
    private Url $baseUri;

    public function __construct(private ?string $selector = null)
    {
    }

    protected function validateAndSanitizeInput(Input $input): Crawler
    {
        $inputValue = $input->get();

        if ($inputValue instanceof RequestResponseAggregate) {
            $this->baseUri = Url::parse($inputValue->effectiveUri());

            return new Crawler($inputValue->response->getBody()->getContents());
        }

        throw new InvalidArgumentException('Input must be an instance of RequestResponseAggregate.');
    }

    protected function invoke(Input $input): array
    {
        $domCrawler = $input->get();

        if ($this->selector) {
            $selector = $this->selector;
            $this->logger->info('Select links with CSS selector: ' . $selector);
        } else {
            $selector = 'a';
            $this->logger->info('Select all links in document.');
        }

        $links = $domCrawler->filter($selector);

        if ($links->count() === 0) {
            return $this->output([], $input);
        }

        $absoluteLinks = [];

        foreach ($links as $link) {
            /** @var DOMElement $link */
            if ($link->nodeName !== 'a') {
                $this->logger->warning('Selector selected <' . $link->nodeName . '> html element. Ignored it.');
                continue;
            }

            $absoluteLinks[] = $this->baseUri->resolve($link->getAttribute('href'))->__toString();
        }

        return $this->output($absoluteLinks, $input);
    }
}
