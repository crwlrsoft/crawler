<?php

namespace Crwlr\Crawler\Steps\Html;

use Crwlr\Crawler\Aggregates\RequestResponseAggregate;
use Crwlr\Crawler\Input;
use Crwlr\Crawler\Steps\Step;
use Generator;
use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\DomCrawler\Crawler;

class QuerySelector extends Step
{
    protected ?string $argument = null;

    public function __construct(
        protected string $selector,
        protected string $getWhat = 'text',
    ) {
    }

    public function html(): self
    {
        $this->getWhat = 'html';
        $this->argument = null;

        return $this;
    }

    public function text(): self
    {
        $this->getWhat = 'text';
        $this->argument = null;

        return $this;
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

    public function outerHtml(): self
    {
        $this->getWhat = 'outerHtml';
        $this->argument = null;

        return $this;
    }

    protected function validateAndSanitizeInput(Input $input): Crawler
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

        throw new InvalidArgumentException('Input must be string, PSR-7 Response or RequestResponseAggregate.');
    }

    /**
     * @param Input $input
     * @return Generator<string|mixed>
     */
    protected function invoke(Input $input): Generator
    {
        $getWhat = $this->getWhat;
        $argument = $this->argument;

        $this->logger->info(
            'Select first element with CSS selector \'' . $this->selector . '\' and get ' . $getWhat . '(' .
            ($argument ?? '') . ')'
        );

        $resultNode = $input->get()->filter($this->selector)->first();

        if ($resultNode->count() > 0) {
            yield $argument ? $resultNode->{$getWhat}($argument) : $resultNode->{$getWhat}();
        }
    }
}
