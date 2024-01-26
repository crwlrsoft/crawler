<?php

namespace Crwlr\Crawler\Steps\Loading;

use Crwlr\Crawler\Loader\Http\Politeness\RobotsTxtHandler;
use Crwlr\RobotsTxt\Exceptions\InvalidRobotsTxtFileException;
use Exception;
use Generator;
use InvalidArgumentException;
use Psr\Http\Message\UriInterface;

class GetSitemapsFromRobotsTxt extends LoadingStep
{
    /**
     * @throws InvalidRobotsTxtFileException
     */
    protected function invoke(mixed $input): Generator
    {
        if (!method_exists($this->loader, 'robotsTxt')) {
            throw new Exception('The Loader doesn\'t expose the RobotsTxtHandler.');
        }

        /** @var RobotsTxtHandler $robotsTxtHandler */
        $robotsTxtHandler = $this->loader->robotsTxt();

        foreach ($robotsTxtHandler->getSitemaps($input) as $sitemapUrl) {
            yield $sitemapUrl;
        }
    }

    /**
     * @throws InvalidArgumentException
     */
    protected function validateAndSanitizeInput(mixed $input): UriInterface
    {
        return $this->validateAndSanitizeToUriInterface($input);
    }
}
