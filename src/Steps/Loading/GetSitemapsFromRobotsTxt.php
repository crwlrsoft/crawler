<?php

namespace Crwlr\Crawler\Steps\Loading;

use Crwlr\Crawler\Loader\Http\HttpLoader;
use Crwlr\Crawler\Steps\Step;
use Crwlr\Crawler\Steps\StepOutputType;
use Crwlr\RobotsTxt\Exceptions\InvalidRobotsTxtFileException;
use Exception;
use Generator;
use InvalidArgumentException;
use Psr\Http\Message\UriInterface;

class GetSitemapsFromRobotsTxt extends Step
{
    use LoadingStep;

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

        $loader = $this->getLoader();

        if (!$loader instanceof HttpLoader) {
            throw new Exception('The GetSitemapsFromRobotsTxt step needs an HttpLoader as loader instance.');
        }

        $robotsTxtHandler = $loader->robotsTxt();

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
