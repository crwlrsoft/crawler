<?php

namespace tests\_Integration\Http;

use Crwlr\Crawler\HttpCrawler;
use Crwlr\Crawler\Loader\Http\HttpLoader;
use Crwlr\Crawler\Loader\Http\Messages\RespondedRequest;
use Crwlr\Crawler\Loader\Http\Politeness\RetryErrorResponseHandler;
use Crwlr\Crawler\Loader\Http\Politeness\RobotsTxtHandler;
use Crwlr\Crawler\Loader\Http\Politeness\Throttler;
use Crwlr\Crawler\Loader\Http\Politeness\TimingUnits\MultipleOf;
use Crwlr\Crawler\Result;
use Crwlr\Crawler\Steps\Loading\Http;
use Crwlr\Crawler\UserAgents\UserAgent;
use Crwlr\Crawler\UserAgents\UserAgentInterface;
use Crwlr\Url\Url;
use Crwlr\Utils\Microseconds;
use GuzzleHttp\Client;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;
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

    public function __construct(
        UserAgentInterface $userAgent,
        ?ClientInterface $httpClient = null,
        ?LoggerInterface $logger = null,
        ?Throttler $throttler = null,
        RetryErrorResponseHandler $retryErrorResponseHandler = new RetryErrorResponseHandler(),
        array $defaultGuzzleClientConfig = []
    ) {
        parent::__construct(
            $userAgent,
            $httpClient,
            $logger,
            $throttler,
            $retryErrorResponseHandler,
            $defaultGuzzleClientConfig,
        );

        $this->robotsTxtHandler = new class ($this, $this->logger) extends RobotsTxtHandler {
            public function isAllowed(UriInterface|Url|string $url): bool
            {
                if (is_string($url)) {
                    $url = Url::parse($url);
                } elseif ($url instanceof UriInterface) {
                    $url = Url::parse($url);
                }

                if ($url->path() === '/not-allowed') {
                    return false;
                }

                return parent::isAllowed($url);
            }
        };
    }

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

        $loader = new TestLoader($userAgent, $client, $logger);

        // To not slow down tests unnecessarily
        $loader->throttle()
            ->waitBetween(new MultipleOf(0.0001), new MultipleOf(0.0002))
            ->waitAtLeast(Microseconds::fromSeconds(0.0001));

        return $loader;
    }

    protected function userAgent(): UserAgentInterface
    {
        return new UserAgent('SomeUserAgent');
    }

    /**
     * This method is here for the return type, so phpstan doesn't complain.
     */
    public function getLoader(): TestLoader
    {
        return parent::getLoader(); // @phpstan-ignore-line
    }
}

/** @var TestCase $this */

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

it(
    'extracts URLs from a sitemap where the <urlset> tag contains attributes that cause symfony DomCrawler to fail',
    function () {
        $crawler = (new Crawler())
            ->input('http://www.example.com/crawling/sitemap2.xml')
            ->addStep(Http::crawl()->inputIsSitemap());

        $crawler->runAndTraverse();

        expect($crawler->getLoader()->loadedUrls)->toHaveCount(7);
    }
);

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

it(
    'keeps the fragment parts in URLs and treats the same URL with a different fragment part as separate URLs when ' .
    'keepUrlFragment() was called',
    function () {
        // Explanation: in almost all cases URLs with a fragment part at the end (#something) will respond with the
        // same content. So, to avoid loading the same page multiple times, the step throws away the fragment part of
        // discovered URLs by default.
        $crawler = (new Crawler())
            ->input('http://www.example.com/crawling/main')
            ->addStep(Http::crawl()->keepUrlFragment()->addToResult(['url']));

        $results = helper_generatorToArray($crawler->run());

        expect($results)->toHaveCount(8);

        $urls = [];

        foreach ($results as $result) {
            $urls[] = $result->get('url');
        }

        expect($urls)->toContain('http://www.example.com/crawling/sub2');

        expect($urls)->toContain('http://www.example.com/crawling/sub2#fragment1');

        expect($urls)->toContain('http://www.example.com/crawling/sub2#fragment2');
    }
);

it('stops crawling when maxOutputs is reached', function () {
    $crawler = (new Crawler())
        ->input('http://www.example.com/crawling/main')
        ->addStep(
            Http::crawl()
                ->keepUrlFragment()
                ->maxOutputs(4)
        );

    $results = helper_generatorToArray($crawler->run());

    expect($results)->toHaveCount(4);

    expect($crawler->getLoader()->loadedUrls)->toHaveCount(4);
});

it('uses canonical links when useCanonicalLinks() is called', function () {
    $crawler = (new Crawler())
        ->input('http://www.example.com/crawling/main')
        ->addStep(
            Http::crawl()
                ->useCanonicalLinks()
                ->addToResult(['url'])
        );

    $results = helper_generatorToArray($crawler->run());

    $resultUrls = array_map(function (Result $result) {
        return $result->get('url');
    }, $results);

    expect($resultUrls)
        ->toBe([
            'http://www.example.com/crawling/main',
            'http://www.example.com/crawling/sub1/sub1',       // actual loaded url was sub1, but canonical is sub1/sub1
            'http://www.example.com/crawling/sub2',
            'http://www.example.com/crawling/sub2/sub1/sub1',
        ])
        ->and($crawler->getLoader()->loadedUrls)
        ->toBe([
            'http://www.example.com/crawling/main',
            'http://www.example.com/crawling/sub1',            // => /crawling/sub1/sub1 => this URL wasn't loaded yet,
            'http://www.example.com/crawling/sub2',            // so when the link is discovered it won't load it.
            'http://www.example.com/crawling/sub2/sub1',       // => /crawling/sub1/sub1 => this URL was already loaded,
            'http://www.example.com/crawling/sub2/sub1/sub1',  // so the response is not yielded as a separate result.
        ]);
});

it('does not yield the same page twice when a URL was redirected to an already loaded page', function () {
    $crawler = (new Crawler())
        ->input('http://www.example.com/crawling/redirect')
        ->addStep(Http::crawl()->addToResult(['url']));

    $results = helper_generatorToArray($crawler->run());

    $resultUrls = array_map(function (Result $result) {
        return $result->get('url');
    }, $results);

    expect($resultUrls)
        ->toContain('http://www.example.com/crawling/main')
        ->and($resultUrls)
        ->not()
        ->toContain('http://www.example.com/crawling/redirect')
        ->and($this->getActualOutputForAssertion())
        ->toContain('Was already loaded before. Do not process this page again.');
});

it('does not produce a fatal error when the initial request fails', function () {
    $crawler = (new Crawler())
        ->input('http://www.example.com/not-allowed')
        ->addStep(Http::crawl()->addToResult(['url']));

    $results = helper_generatorToArray($crawler->run());

    expect($results)->toHaveCount(0);
});
