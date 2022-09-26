<?php

namespace Crwlr\Crawler\Loader\Http\Politeness;

use Crwlr\Crawler\Loader\Http\HttpLoader;
use Crwlr\Crawler\Loader\Loader;
use Crwlr\Crawler\Steps\Loading\Http;
use Crwlr\Crawler\UserAgents\BotUserAgent;
use Crwlr\Crawler\UserAgents\UserAgentInterface;
use Crwlr\RobotsTxt\RobotsTxt;
use Crwlr\Url\Url;
use Exception;
use Psr\Http\Message\UriInterface;

class RobotsTxtHandler
{
    protected UserAgentInterface $userAgent;

    /**
     * @var array<string, RobotsTxt>
     */
    protected array $robotsTxts = [];

    protected bool $ignoreWildcardRules = false;

    public function __construct(protected Loader $loader)
    {
        $this->userAgent = $this->loader->userAgent();
    }

    public function ignoreWildcardRules(): void
    {
        $this->ignoreWildcardRules = true;
    }

    /**
     * @throws Exception
     */
    public function isAllowed(string|UriInterface|Url $url): bool
    {
        if (!$this->userAgent instanceof BotUserAgent) {
            return true;
        }

        $url = $this->getUrlInstance($url);

        if ($url->path() === '/robots.txt') {
            return true;
        }

        $robotsTxt = $this->getRobotsTxtFor($url);

        if ($this->ignoreWildcardRules) {
            return !$robotsTxt->isExplicitlyNotAllowedFor($url, $this->userAgent->productToken());
        }

        return $robotsTxt->isAllowed($url, $this->userAgent->productToken());
    }

    /**
     * @return string[]
     */
    public function getSitemaps(string|UriInterface|Url $url): array
    {
        return $this->getRobotsTxtFor($url)->sitemaps();
    }

    protected function getRobotsTxtFor(string|UriInterface|Url $url): RobotsTxt
    {
        $url = $this->getUrlInstance($url);

        $root = $url->root();

        if (isset($this->robotsTxts[$root])) {
            return $this->robotsTxts[$root];
        }

        $robotsTxtContent = $this->loadRobotsTxtContent($root . '/robots.txt');

        $this->robotsTxts[$root] = RobotsTxt::parse($robotsTxtContent);

        return $this->robotsTxts[$root];
    }

    protected function loadRobotsTxtContent(string $robotsTxtUrl): string
    {
        $usedHeadlessBrowser = false;

        if ($this->loader instanceof HttpLoader) {
            // If loader is set to use headless browser, temporary switch to using PSR-18 HTTP Client.
            $usedHeadlessBrowser = $this->loader->usesHeadlessBrowser();

            $this->loader->useHttpClient();
        }

        $response = $this->loader->load($robotsTxtUrl);

        if ($this->loader instanceof HttpLoader && $usedHeadlessBrowser) {
            $this->loader->useHeadlessBrowser();
        }

        return $response ? Http::getBodyString($response) : '';
    }

    protected function getUrlInstance(string|UriInterface|Url $url): Url
    {
        if (is_string($url) || $url instanceof UriInterface) {
            return Url::parse($url);
        }

        return $url;
    }
}
