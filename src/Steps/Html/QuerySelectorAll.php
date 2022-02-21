<?php

namespace Crwlr\Crawler\Steps\Html;

use Crwlr\Crawler\Input;
use Exception;
use Symfony\Component\DomCrawler\Crawler;

class QuerySelectorAll extends QuerySelector
{
    /**
     * @param Input $input
     * @return array|mixed
     * @throws Exception
     */
    protected function invoke(Input $input): mixed
    {
        $getWhat = $this->getWhat;
        $argument = $this->argument;

        $this->logger->info(
            'Select all elements with CSS selector \'' . $this->selector . '\' and get ' . $getWhat . '(' .
            ($argument ?? '') . ')'
        );

        return $input->get()->filter($this->selector)->each(function (Crawler $node) use ($getWhat, $argument) {
            if ($argument) {
                return $node->{$getWhat}($argument);
            }

            return $node->{$getWhat}();
        });
    }
}
