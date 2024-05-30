<?php

namespace Crwlr\Crawler\Steps\Loading\Http;

use Crwlr\Crawler\Loader\Http\Messages\RespondedRequest;
use Crwlr\Crawler\Steps\Html\DomQuery;
use Crwlr\Crawler\Steps\Loading\Http;
use Crwlr\Url\Url;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\DomCrawler\Crawler;

final class Document
{
    private Crawler $dom;

    private Url $url;

    private Url $baseUrl;

    private ?Url $canonicalUrl = null;

    public function __construct(
        private readonly RespondedRequest $respondedRequest,
        private readonly ?LoggerInterface $logger = null,
    ) {
        $responseBody = Http::getBodyString($this->respondedRequest);

        $this->dom = new Crawler($responseBody);

        $this->setBaseUrl();
    }

    public function dom(): Crawler
    {
        return $this->dom;
    }

    public function url(): Url
    {
        return $this->url;
    }

    public function baseUrl(): Url
    {
        return $this->baseUrl;
    }

    public function canonicalUrl(): string
    {
        if ($this->canonicalUrl === null) {
            $canonicalLinkElement = $this->dom->filter('link[rel=canonical]')->first();

            if ($canonicalLinkElement->count() > 0) {
                $canonicalHref = $canonicalLinkElement->first()->attr('href');

                if ($canonicalHref) {
                    try {
                        $this->canonicalUrl = $this->baseUrl->resolve($canonicalHref);
                    } catch (Exception $exception) {
                        $this->logger?->warning(
                            'Failed to resolve canonical link href value against the document base URL.',
                        );
                    }
                }
            }

            $this->canonicalUrl = $this->canonicalUrl ?? $this->url;
        }

        return $this->canonicalUrl;
    }

    private function setBaseUrl(): void
    {
        $this->url = Url::parse($this->respondedRequest->effectiveUri());

        $this->baseUrl = $this->url;

        $documentBaseHref = DomQuery::getBaseHrefFromDocument($this->dom);

        if ($documentBaseHref) {
            try {
                $this->baseUrl = $this->baseUrl->resolve($documentBaseHref);
            } catch (Exception $exception) {
                $this->logger?->warning('Failed to resolve the document <base> tag href against the document URL.');
            }
        }
    }
}
