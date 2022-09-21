<?php

namespace tests\_Integration\Http;

use Crwlr\Crawler\HttpCrawler;
use Crwlr\Crawler\Loader\Http\HttpLoader;
use Crwlr\Crawler\Loader\Http\Messages\RespondedRequest;
use Crwlr\Crawler\Steps\Loading\Http;
use Crwlr\Crawler\UserAgents\BotUserAgent;
use Crwlr\Crawler\UserAgents\UserAgentInterface;
use Crwlr\Url\Url;
use GuzzleHttp\Client;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;

use function tests\helper_generatorToArray;

/**
 * A TestLoader that tracks all the loaded URLs in a public property.
 */

class TestLoader extends HttpLoader
{
    /**
     * @var string[]
     */
    public array $loadedUrls = [];

    public function load(mixed $subject): ?RespondedRequest
    {
        $request = $this->validateSubjectType($subject);

        $this->loadedUrls[] = $request->getUri()->__toString();

        return parent::load($subject);
    }
}

/**
 * To check if the Crawler stays on the same host or same domain when crawling, the PSR-18 HTTP ClientInterface
 * of this Crawler's Loader, replaces the host in the request URI just before sending the Request. The Loader thinks
 * it actually loaded the page from the incoming URI and the returned RespondedRequest object also has that original URI
 * as effectiveUri (except if the requested page redirects).
 */

class Crawler extends HttpCrawler
{
    public function loader(UserAgentInterface $userAgent, LoggerInterface $logger): TestLoader
    {
        $client = new class () implements ClientInterface {
            private Client $guzzleClient;

            public function __construct()
            {
                $this->guzzleClient = new Client();
            }

            public function sendRequest(RequestInterface $request): ResponseInterface
            {
                $request = $request->withUri($request->getUri()->withHost('localhost')->withPort(8000));

                return $this->guzzleClient->sendRequest($request);
            }
        };

        return new TestLoader($userAgent, $client, $logger);
    }

    protected function userAgent(): UserAgentInterface
    {
        return new BotUserAgent('TestBot');
    }

    public function getLoader(): TestLoader
    {
        return parent::getLoader(); // @phpstan-ignore-line
    }
}

it('stays on the same host by default', function () {
    $crawler = (new Crawler())
        ->input('http://www.example.com/crawling/main')
        ->addStep(Http::crawl());

    $crawler->runAndTraverse();

    expect($crawler->getLoader()->loadedUrls)->not()->toContain('http://foo.example.com/crawling/main-on-subdomain');
});

it('stays on the same domain when method sameDomain() is called', function () {
    $crawler = (new Crawler())
        ->input('http://www.example.com/crawling/main')
        ->addStep(Http::crawl()->sameDomain());

    $crawler->runAndTraverse();

    expect($crawler->getLoader()->loadedUrls)->toContain('http://foo.example.com/crawling/main-on-subdomain');

    expect($crawler->getLoader()->loadedUrls)->not()->toContain('https://www.crwlr.software/packages/crawler');
});

it('stays on the same host when method sameHost() is called', function () {
    $crawler = (new Crawler())
        ->input('http://www.example.com/crawling/main')
        ->addStep(
            Http::crawl()
                ->sameDomain()
                ->sameHost()
        );

    $crawler->runAndTraverse();

    expect($crawler->getLoader()->loadedUrls)->not()->toContain('http://foo.example.com/crawling/main-on-subdomain');
});

it('crawls every page of a website that is linked somewhere', function () {
    $crawler = (new Crawler())
        ->input('http://www.example.com/crawling/main')
        ->addStep(Http::crawl());

    $crawler->runAndTraverse();

    expect($crawler->getLoader()->loadedUrls)->toHaveCount(6);

    expect($crawler->getLoader()->loadedUrls)->toContain('http://www.example.com/crawling/main');

    expect($crawler->getLoader()->loadedUrls)->toContain('http://www.example.com/crawling/sub1');

    expect($crawler->getLoader()->loadedUrls)->toContain('http://www.example.com/crawling/sub1/sub1');

    expect($crawler->getLoader()->loadedUrls)->toContain('http://www.example.com/crawling/sub2');

    expect($crawler->getLoader()->loadedUrls)->toContain('http://www.example.com/crawling/sub2/sub1');

    expect($crawler->getLoader()->loadedUrls)->toContain('http://www.example.com/crawling/sub2/sub1/sub1');
});

it('crawls only to a certain depth when the crawl depth is defined', function () {
    $crawler = (new Crawler())
        ->input('http://www.example.com/crawling/main')
        ->addStep(Http::crawl()->depth(1));

    $crawler->runAndTraverse();

    expect($crawler->getLoader()->loadedUrls)->toHaveCount(3);

    $crawler = (new Crawler())
        ->input('http://www.example.com/crawling/main')
        ->addStep(Http::crawl()->depth(2));

    $crawler->runAndTraverse();

    expect($crawler->getLoader()->loadedUrls)->toHaveCount(5);
});

it('extracts URLs from a sitemap if you call method inputIsSitemap()', function () {
    $crawler = (new Crawler())
        ->input('http://www.example.com/crawling/sitemap.xml')
        ->addStep(Http::crawl()->inputIsSitemap());

    $crawler->runAndTraverse();

    expect($crawler->getLoader()->loadedUrls)->toHaveCount(7);
});

it('fails to extract URLs if you provide a sitemap as input and don\'t call inputIsSitemap()', function () {
    $crawler = (new Crawler())
        ->input('http://www.example.com/crawling/sitemap.xml')
        ->addStep(Http::crawl());

    $crawler->runAndTraverse();

    expect($crawler->getLoader()->loadedUrls)->toHaveCount(1);
});

it('loads only pages where the path starts with a certain string when method pathStartsWith() is called', function () {
    $crawler = (new Crawler())
        ->input('http://www.example.com/crawling/sitemap.xml')
        ->addStep(
            Http::crawl()
                ->inputIsSitemap()
                ->pathStartsWith('/crawling/sub1')
        );

    $crawler->runAndTraverse();

    expect($crawler->getLoader()->loadedUrls)->toHaveCount(3);

    expect($crawler->getLoader()->loadedUrls)->toContain('http://www.example.com/crawling/sitemap.xml');

    expect($crawler->getLoader()->loadedUrls)->toContain('http://www.example.com/crawling/sub1');

    expect($crawler->getLoader()->loadedUrls)->toContain('http://www.example.com/crawling/sub1/sub1');
});

it('loads only URLs where the path matches a regex when method pathMatches() is used', function () {
    $crawler = (new Crawler())
        ->input('http://www.example.com/crawling/sitemap.xml')
        ->addStep(
            Http::crawl()
                ->inputIsSitemap()
                ->pathMatches('/^\/crawling\/sub[12]$/')
        );

    $crawler->runAndTraverse();

    expect($crawler->getLoader()->loadedUrls)->toHaveCount(3);
});

it('loads only URLs where the Closure passed to method customFilter() returns true', function () {
    $crawler = (new Crawler())
        ->input('http://www.example.com/crawling/sitemap.xml')
        ->addStep(
            Http::crawl()
                ->inputIsSitemap()
                ->customFilter(function (Url $url) {
                    return in_array($url->path(), [
                        '/crawling/main',
                        '/crawling/sub1/sub1',
                        '/crawling/sub2/sub1/sub1'
                    ], true);
                })
        );

    $crawler->runAndTraverse();

    expect($crawler->getLoader()->loadedUrls)->toHaveCount(4);

    expect($crawler->getLoader()->loadedUrls)->toContain('http://www.example.com/crawling/main');

    expect($crawler->getLoader()->loadedUrls)->toContain('http://www.example.com/crawling/sub1/sub1');

    expect($crawler->getLoader()->loadedUrls)->toContain('http://www.example.com/crawling/sub2/sub1/sub1');
});

it(
    'receives the link element where the URL was found, as second param in the Closure passed to method ' .
    'customFilter() when it was found in an HTML document',
    function () {
        $crawler = (new Crawler())
            ->input('http://www.example.com/crawling/main')
            ->addStep(
                Http::crawl()
                    ->customFilter(function (Url $url, ?\Symfony\Component\DomCrawler\Crawler $linkElement) {
                        return $linkElement && str_contains($linkElement->text(), 'Subpage 2');
                    })
            );

        $crawler->runAndTraverse();

        expect($crawler->getLoader()->loadedUrls)->toHaveCount(4);

        expect($crawler->getLoader()->loadedUrls)->toContain('http://www.example.com/crawling/main');

        expect($crawler->getLoader()->loadedUrls)->toContain('http://www.example.com/crawling/sub2');

        expect($crawler->getLoader()->loadedUrls)->toContain('http://www.example.com/crawling/sub2/sub1');

        expect($crawler->getLoader()->loadedUrls)->toContain('http://www.example.com/crawling/sub2/sub1/sub1');
    }
);

it(
    'loads all pages, but yields only responses where the URL path starts with a certain string, when methods ' .
    'pathStartsWith() and loadAllButYieldOnlyMatching() are called',
    function () {
        $crawler = (new Crawler())
            ->input('http://www.example.com/crawling/sitemap.xml')
            ->addStep(
                Http::crawl()
                    ->inputIsSitemap()
                    ->pathStartsWith('/crawling/sub2')
                    ->loadAllButYieldOnlyMatching()
            );

        $results = helper_generatorToArray($crawler->run());

        expect($crawler->getLoader()->loadedUrls)->toHaveCount(7);

        expect($results)->toHaveCount(3);
    }
);

it(
    'loads all URLs, but yields only responses where the URL path matches a regex, when methods pathMatches() and ' .
    'loadAllButYieldOnlyMatching() are called',
    function () {
        $crawler = (new Crawler())
            ->input('http://www.example.com/crawling/sitemap.xml')
            ->addStep(
                Http::crawl()
                    ->inputIsSitemap()
                    ->pathMatches('/^\/crawling\/sub[12]$/')
                    ->loadAllButYieldOnlyMatching()
            );

        $results = helper_generatorToArray($crawler->run());

        expect($crawler->getLoader()->loadedUrls)->toHaveCount(7);

        expect($results)->toHaveCount(2);
    }
);

it(
    'loads all URLs but yields only responses where the Closure passed to method customFilter() returns true, when ' .
    'methods customFilter() and loadAllButYieldOnlyMatching() are called',
    function () {
        $crawler = (new Crawler())
            ->input('http://www.example.com/crawling/sitemap.xml')
            ->addStep(
                Http::crawl()
                    ->inputIsSitemap()
                    ->customFilter(function (Url $url) {
                        return in_array($url->path(), [
                            '/crawling/main',
                            '/crawling/sub1/sub1',
                            '/crawling/sub2/sub1/sub1'
                        ], true);
                    })
                    ->loadAllButYieldOnlyMatching()
            );

        $results = helper_generatorToArray($crawler->run());

        expect($crawler->getLoader()->loadedUrls)->toHaveCount(7);

        expect($results)->toHaveCount(3);
    }
);
