<?php

namespace Crwlr\Crawler\Steps\Html;

use Crwlr\Crawler\Loader\Http\Messages\RespondedRequest;
use Crwlr\Crawler\Steps\Step;
use Crwlr\Url\Url;
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

    protected function validateAndSanitizeInput(mixed $input): Crawler
    {
        if ($input instanceof RespondedRequest) {
            $this->baseUri = Url::parse($input->effectiveUri());

            return new Crawler($input->response->getBody()->getContents());
        }

        throw new InvalidArgumentException('Input must be an instance of RequestResponseAggregate.');
    }

    /**
     * @param Crawler $input
     * @return Generator<string>
     * @throws Exception
     */
    protected function invoke(mixed $input): Generator
    {
        $selector = $this->selector ?? 'a';

        $this->logger?->info(
            $this->selector === null ? 'Select first link in document.' : 'Select link with CSS selector: ' . $selector
        );

        foreach ($input->filter($selector) as $link) {
            if ($link->nodeName !== 'a') {
                $this->logger?->warning('Selector matched <' . $link->nodeName . '> html element. Ignored it.');

                continue;
            }

            $link = new Crawler($link);

            yield $this->baseUri->resolve($link->attr('href') ?? '')->__toString();
            break;
        }
    }
}
