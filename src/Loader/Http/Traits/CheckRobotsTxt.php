<?php

namespace Crwlr\Crawler\Loader\Http\Traits;

use Crwlr\Crawler\Loader\Http\Messages\RespondedRequest;
use Crwlr\Crawler\Loader\Http\Exceptions\LoadingException;
use Crwlr\Crawler\Steps\Loading\Http;
use Crwlr\Crawler\UserAgents\BotUserAgentInterface;
use Crwlr\Crawler\UserAgents\UserAgentInterface;
use Crwlr\RobotsTxt\Exceptions\InvalidRobotsTxtFileException;
use Crwlr\RobotsTxt\RobotsTxt;
use Crwlr\Url\Url;
use Exception;
use Psr\Http\Message\UriInterface;
use Psr\Log\LoggerInterface;

trait CheckRobotsTxt
{
    /**
     * Cache parsed robots.txt files by <protocol>://<authority>
     *
     * @var array|(RobotsTxt|null)[]
     */
    protected array $robotsTxts = [];

    abstract public function load(mixed $subject): mixed;
    abstract protected function userAgent(): UserAgentInterface;
    abstract protected function logger(): LoggerInterface;

    /**
     * @throws LoadingException
     * @throws Exception
     */
    public function isAllowedToBeLoaded(UriInterface $uri, bool $throwsException = false): bool
    {
        if (str_ends_with($uri, 'robots.txt')) {
            return true;
        }

        $parsedRobotsTxt = $this->getParsedRobotsTxtFor($uri);

        if (!$this->userAgent() instanceof BotUserAgentInterface) {
            throw new Exception('CheckRobotsTxt trait only works with a BotUserAgent');
        }

        if ($parsedRobotsTxt && !$parsedRobotsTxt->isAllowed($uri, $this->userAgent()->productToken())) {
            $message = 'Crawler ist not allowed to load ' . $uri . ' according to the robots.txt file.';
            $this->logger()->warning($message);

            if ($throwsException) {
                throw new LoadingException($message);
            }

            return false;
        }

        return true;
    }

    /**
     * This is a default implementation for the HttpLoader.
     * When you're using the trait in a different Loader you will need to make a custom implementation of this method.
     *
     * @param string $uri
     * @return string|null
     * @throws Exception
     */
    protected function loadRobotsTxtContent(string $uri): ?string
    {
        $respondedRequest = $this->load($uri);

        if (method_exists($this, 'waitUntilNextRequestCanBeSent')) {
            $this->waitUntilNextRequestCanBeSent();
        }

        if ($respondedRequest instanceof RespondedRequest) {
            return Http::getBodyString($respondedRequest);
        }

        return null;
    }

    /**
     * @throws Exception
     */
    private function getParsedRobotsTxtFor(UriInterface $uri): ?RobotsTxt
    {
        $robotsTxtLocation = $this->getRobotsTxtLocationFor($uri);

        if (!array_key_exists($robotsTxtLocation, $this->robotsTxts)) {
            $robotsTxtContent = $this->loadRobotsTxtContent($robotsTxtLocation);

            if (!$robotsTxtContent) {
                $this->robotsTxts[$robotsTxtLocation] = null;
            } else {
                try {
                    $this->robotsTxts[$robotsTxtLocation] = RobotsTxt::parse($robotsTxtContent);
                } catch (InvalidRobotsTxtFileException $exception) {
                    $this->logger()->warning('Failed to parse robots.txt file: ' . $exception->getMessage());
                    $this->robotsTxts[$robotsTxtLocation] = null;
                }
            }
        }

        return $this->robotsTxts[$robotsTxtLocation];
    }

    /**
     * @throws Exception
     */
    private function getRobotsTxtLocationFor(UriInterface $uri): string
    {
        return Url::parse($uri->__toString())->root() . '/robots.txt';
    }
}
