<?php

namespace Crwlr\Crawler\Steps\Html;

use Crwlr\Crawler\Input;
use Symfony\Component\DomCrawler\Crawler;

class QuerySelectorAll extends QuerySelector
{
    protected function invoke(Input $input): array
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
