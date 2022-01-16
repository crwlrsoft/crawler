<?php

namespace Crwlr\Crawler\Steps\Html;

use Crwlr\Crawler\Aggregates\RequestResponseAggregate;
use Crwlr\Crawler\Input;
use Crwlr\Crawler\Steps\Step;
use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\DomCrawler\Crawler;

class QuerySelectorAll extends Step
{
    private string $getWhat = 'text';
    private ?string $argument;

    public function __construct(private string $selector)
    {
    }

    public function innerText(): self
    {
        $this->getWhat = 'innerText';
        $this->argument = null;

        return $this;
    }

    public function attribute(string $attributeName): self
    {
        $this->getWhat = 'attr';
        $this->argument = $attributeName;

        return $this;
    }

    public function validateAndSanitizeInput(Input $input): Crawler
    {
        $inputValue = $input->get();

        if (is_string($inputValue)) {
            return new Crawler($inputValue);
        }

        if ($inputValue instanceof ResponseInterface) {
            return new Crawler($inputValue->getBody()->getContents());
        }

        if ($inputValue instanceof RequestResponseAggregate) {
            return new Crawler($inputValue->response->getBody()->getContents());
        }

        throw new InvalidArgumentException('Input must be string or an instance of the PSR-7 ResponseInterface');
    }

    public function invoke(Input $input): array
    {
        $getWhat = $this->getWhat;
        $argument = $this->argument;

        $this->logger->info(
            'Select all elements with CSS selector \'' . $this->selector . '\' and get ' . $getWhat . '(' .
            ($argument ?? '') . ')'
        );

        $results = $input->get()->filter($this->selector)->each(function (Crawler $node) use ($getWhat, $argument) {
            if ($argument) {
                return $node->{$getWhat}($argument);
            }

            return $node->{$getWhat}();
        });

        return $this->output($results, $input);
    }
}
