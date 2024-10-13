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
    /**
     * @use LoadingStep<HttpLoader>
     */
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
        $loader = $this->getLoader();

        if (!method_exists($loader, 'robotsTxt')) {
            throw new Exception('The Loader doesn\'t expose the RobotsTxtHandler.');
        }

        if (!$loader instanceof HttpLoader) {
            throw new Exception('The GetSitemapsFromRobotsTxt step needs an HttpLoader as loader instance.');
        }

        $robotsTxtHandler = $this->getLoader()->robotsTxt();

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
