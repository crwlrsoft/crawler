<?php

namespace Crwlr\Crawler\Steps\Html;

use Crwlr\Crawler\Aggregates\RequestResponseAggregate;
use Crwlr\Crawler\Input;
use Crwlr\Crawler\Steps\Step;
use Crwlr\Url\Url;
use DOMElement;
use Exception;
use Generator;
use InvalidArgumentException;
use Symfony\Component\DomCrawler\Crawler;

class GetLink extends Step
{
    protected Url $baseUri;

    public function __construct(protected ?string $selector = null)
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

    /**
     * @param Input $input
     * @return Generator<string>
     * @throws Exception
     */
    protected function invoke(Input $input): Generator
    {
        $selector = $this->selector ?? 'a';
        $this->logger->info(
            $this->selector === null ? 'Select first link in document.' : 'Select link with CSS selector: ' . $selector
        );

        foreach ($input->get()->filter($selector) as $link) {
            /** @var DOMElement $link */
            if ($link->nodeName !== 'a') {
                $this->logger->warning('Selector matched <' . $link->nodeName . '> html element. Ignored it.');
                continue;
            }

            yield $this->baseUri->resolve($link->getAttribute('href'))->__toString();
            break;
        }
    }
}
