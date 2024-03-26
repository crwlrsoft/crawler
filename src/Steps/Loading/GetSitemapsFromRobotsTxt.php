<?php

namespace Crwlr\Crawler\Steps\Loading;

use Crwlr\Crawler\Steps\StepOutputType;
use Crwlr\RobotsTxt\Exceptions\InvalidRobotsTxtFileException;
use Exception;
use Generator;
use InvalidArgumentException;
use Psr\Http\Message\UriInterface;

class GetSitemapsFromRobotsTxt extends LoadingStep
{
    public function outputType(): StepOutputType
    {
        return StepOutputType::Scalar;
    }

    /**
     * @throws InvalidRobotsTxtFileException|Exception
     */
    protected function invoke(mixed $input): Generator
    {
        if (!method_exists($this->loader, 'robotsTxt')) {
            throw new Exception('The Loader doesn\'t expose the RobotsTxtHandler.');
        }

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
