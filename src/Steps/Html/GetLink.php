<?php

namespace Crwlr\Crawler\Steps\Html;

use Crwlr\Crawler\Loader\Http\Messages\RespondedRequest;
use Crwlr\Crawler\Steps\Loading\Http;
use Crwlr\Crawler\Steps\Step;
use Crwlr\Url\Url;
use DOMNode;
use Exception;
use Generator;
use InvalidArgumentException;
use Symfony\Component\DomCrawler\Crawler;

class GetLink extends Step
{
    protected Url $baseUri;

    protected ?bool $onSameDomain = null;

    /**
     * @var null|string[]
     */
    protected ?array $onDomain = null;

    protected ?bool $onSameHost = null;

    /**
     * @var null|string[]
     */
    protected ?array $onHost = null;

    protected bool $withFragment = true;

    public function __construct(protected ?string $selector = null)
    {
    }

    public static function isSpecialNonHttpLink(Crawler $linkElement): bool
    {
        $href = $linkElement->attr('href') ?? '';

        return str_starts_with($href, 'mailto:') ||
            str_starts_with($href, 'tel:') ||
            str_starts_with($href, 'javascript:');
    }

    protected function validateAndSanitizeInput(mixed $input): Crawler
    {
        if (!$input instanceof RespondedRequest) {
            throw new InvalidArgumentException('Input must be an instance of RespondedRequest.');
        }

        $this->baseUri = Url::parse($input->effectiveUri());

        return new Crawler(Http::getBodyString($input));
    }

    /**
     * @param Crawler $input
     * @return Generator<string>
     * @throws Exception
     */
    protected function invoke(mixed $input): Generator
    {
        $this->getBaseFromDocument($input);

        $selector = $this->selector ?? 'a';

        foreach ($input->filter($selector) as $link) {
            $linkUrl = $this->getLinkUrl($link);

            if ($linkUrl) {
                yield (string) $linkUrl;

                break;
            }
        }
    }

    public function onSameDomain(): static
    {
        $this->onSameDomain = true;

        return $this;
    }

    public function notOnSameDomain(): static
    {
        $this->onSameDomain = false;

        return $this;
    }

    /**
     * @param string|string[] $domains
     * @return $this
     */
    public function onDomain(string|array $domains): static
    {
        if (is_array($domains) && !$this->isArrayWithOnlyStrings($domains)) {
            throw new InvalidArgumentException('You can only set domains from string values');
        }

        $domains = is_string($domains) ? [$domains] : $domains;

        $this->onDomain = $this->onDomain ? array_merge($this->onDomain, $domains) : $domains;

        return $this;
    }

    public function onSameHost(): static
    {
        $this->onSameHost = true;

        return $this;
    }

    public function notOnSameHost(): static
    {
        $this->onSameHost = false;

        return $this;
    }

    /**
     * @param string|string[] $hosts
     */
    public function onHost(string|array $hosts): static
    {
        if (is_array($hosts) && !$this->isArrayWithOnlyStrings($hosts)) {
            throw new InvalidArgumentException('You can only set hosts from string values');
        }

        $hosts = is_string($hosts) ? [$hosts] : $hosts;

        $this->onHost = $this->onHost ? array_merge($this->onHost, $hosts) : $hosts;

        return $this;
    }

    public function withoutFragment(): static
    {
        $this->withFragment = false;

        return $this;
    }

    protected function getBaseFromDocument(Crawler $document): void
    {
        $baseHref = DomQuery::getBaseHrefFromDocument($document);

        if (!empty($baseHref)) {
            $this->baseUri = $this->baseUri->resolve($baseHref);
        }
    }

    /**
     * @throws Exception
     */
    protected function getLinkUrl(DOMNode $link): ?Url
    {
        if ($link->nodeName !== 'a') {
            $this->logger?->warning('Selector matched <' . $link->nodeName . '> html element. Ignored it.');

            return null;
        }

        $link = new Crawler($link);

        if (self::isSpecialNonHttpLink($link)) {
            return null;
        }

        $linkUrl = $this->handleUrlFragment(
            $this->baseUri->resolve($link->attr('href') ?? '')
        );

        if ($this->matchesAdditionalCriteria($linkUrl)) {
            return $linkUrl;
        }

        return null;
    }

    /**
     * @throws Exception
     */
    protected function matchesAdditionalCriteria(Url $link): bool
    {
        return ($this->onSameDomain === null || $this->isOnSameDomain($link)) &&
            ($this->onSameHost === null || $this->isOnSameHost($link)) &&
            ($this->onDomain === null || $this->isOnDomain($link)) &&
            ($this->onHost === null || $this->isOnHost($link));
    }

    protected function isOnSameDomain(Url $link): bool
    {
        return ($this->onSameDomain && $this->baseUri->isDomainEqualIn($link)) ||
            ($this->onSameDomain === false && !$this->baseUri->isDomainEqualIn($link));
    }

    protected function isOnSameHost(Url $link): bool
    {
        return ($this->onSameHost && $this->baseUri->isHostEqualIn($link)) ||
            ($this->onSameHost === false && !$this->baseUri->isHostEqualIn($link));
    }

    /**
     * @throws Exception
     */
    protected function isOnDomain(Url $link): bool
    {
        if (is_array($this->onDomain)) {
            foreach ($this->onDomain as $domain) {
                if ($link->domain() === $domain) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @throws Exception
     */
    protected function isOnHost(Url $link): bool
    {
        if (is_array($this->onHost)) {
            foreach ($this->onHost as $host) {
                if ($link->host() === $host) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param mixed[] $array
     * @return bool
     */
    protected function isArrayWithOnlyStrings(array $array): bool
    {
        foreach ($array as $element) {
            if (!is_string($element)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @throws Exception
     */
    protected function handleUrlFragment(Url $url): Url
    {
        if (!$this->withFragment) {
            $url->fragment('');
        }

        return $url;
    }
}
