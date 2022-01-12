<?php

namespace Crwlr\Crawler\Steps\Html;

use Crwlr\Crawler\Input;
use Crwlr\Crawler\Steps\Step;
use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\DomCrawler\Crawler;

class QuerySelector extends Step
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

        // string, http response,
        if (is_string($inputValue)) {
            return new Crawler($inputValue);
        }

        if ($inputValue instanceof ResponseInterface) {
            return new Crawler($inputValue->getBody()->getContents());
        }

        throw new InvalidArgumentException('Input must be string or an instance of the PSR-7 ResponseInterface');
    }

    public function invoke(Input $input): array
    {
        $domCrawler = $input->get();
        $element = $domCrawler->filter($this->selector)->first();

        if ($element->count() === 0) {
            return $this->output('', $input);
        }

        if ($this->argument) {
            return $this->output(
                $element->{$this->getWhat}($this->argument),
                $input
            );
        }

        return $this->output(
            $element->{$this->getWhat}(),
            $input
        );
    }
}
