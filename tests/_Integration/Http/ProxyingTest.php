<?php

use Crwlr\Crawler\Result;
use Crwlr\Crawler\Steps\Loading\Http;
use Symfony\Component\Process\Process;

use function tests\helper_getFastCrawler;

class ProxyServerProcesses
{
    public const PORTS = [8001, 8002, 8003];

    /**
     * @var array<int, ?Process>
     */
    public static array $processes = [8001 => null, 8002 => null, 8003 => null];
}

beforeEach(function () {
    $startedProcesses = false;

    foreach (ProxyServerProcesses::PORTS as $port) {
        if (!ProxyServerProcesses::$processes[$port]) {
            ProxyServerProcesses::$processes[$port] = Process::fromShellCommandline(
                'php -S localhost:' . $port . ' ' . __DIR__ . '/../ProxyServer.php',
            );

            ProxyServerProcesses::$processes[$port]->start();

            $startedProcesses = true;
        }
    }

    if ($startedProcesses) {
        usleep(100_000);
    }
});

afterAll(function () {
    foreach (ProxyServerProcesses::PORTS as $port) {
        ProxyServerProcesses::$processes[$port]?->stop(3, SIGINT);

        ProxyServerProcesses::$processes[$port] = null;
    }
});

it('uses a proxy when the useProxy() method of the loader was called', function () {
    $crawler = helper_getFastCrawler();

    $crawler->getLoader()->useProxy('http://localhost:8001');

    $crawler
        ->input('http://www.crwlr.software/packages')
        ->addStep(Http::get()->keep(['body']));

    $results = iterator_to_array($crawler->run());

    expect($results[0])
        ->toBeInstanceOf(Result::class)
        ->and($results[0]->get('body'))
        ->toContain('Proxy Server Response for http://www.crwlr.software/packages');
});

it('uses correct method, headers and HTTP version in the proxied request', function () {
    $crawler = helper_getFastCrawler();

    $crawler->getLoader()->useProxy('http://localhost:8001');

    $crawler
        ->input('http://www.crwlr.software/packages')
        ->addStep(
            Http::put(['Accept-Encoding' => 'gzip, deflate, br'], 'Hello World', '1.0')
                ->keep(['body']),
        );

    $results = iterator_to_array($crawler->run());

    expect($results[0])
        ->toBeInstanceOf(Result::class)
        ->and($results[0]->get('body'))
        ->toContain('Protocol Version: HTTP/1.0')
        ->toContain('Request Method: PUT')
        ->toContain('Request Body: Hello World')
        ->toContain('["Accept-Encoding"]=>' . PHP_EOL . '  string(17) "gzip, deflate, br"');
});

it('uses rotating proxies when the useRotatingProxies() method of the loader was called', function () {
    $crawler = helper_getFastCrawler();

    $crawler->getLoader()->useRotatingProxies([
        'http://localhost:8001',
        'http://localhost:8002',
        'http://localhost:8003',
    ]);

    $crawler
        ->input([
            'http://www.crwlr.software/packages/crawler/v1.1/getting-started',
            'http://www.crwlr.software/packages/url/v2.0/getting-started',
            'http://www.crwlr.software/packages/query-string/v1.0/getting-started',
            'http://www.crwlr.software/packages/robots-txt/v1.1/getting-started',
        ])
        ->addStep(Http::get()->keep(['body']));

    $results = iterator_to_array($crawler->run());

    expect($results)->toHaveCount(4)
        ->and($results[0])
        ->toBeInstanceOf(Result::class)
        ->and($results[0]->get('body'))
        ->toContain('Port: 8001')           // First request with first proxy
        ->and($results[1])
        ->toBeInstanceOf(Result::class)
        ->and($results[1]->get('body'))
        ->toContain('Port: 8002')           // Second request with second proxy
        ->and($results[2])
        ->toBeInstanceOf(Result::class)
        ->and($results[2]->get('body'))
        ->toContain('Port: 8003')           // Third request with third proxy
        ->and($results[3])
        ->toBeInstanceOf(Result::class)
        ->and($results[3]->get('body'))
        ->toContain('Port: 8001');          // And finally the fourth request with the first proxy again.
});

it('can also use a proxy when using the headless browser', function () {
    $crawler = helper_getFastCrawler();

    $crawler
        ->getLoader()
        ->useHeadlessBrowser()
        ->useProxy('http://localhost:8001');

    $crawler
        ->input('http://www.crwlr.software/blog')
        ->addStep(
            Http::get(['Accept-Language' => 'de-DE,de;q=0.9,en-US;q=0.8,en;q=0.7'])
                ->keep(['body']),
        );

    $results = iterator_to_array($crawler->run());

    expect($results[0])
        ->toBeInstanceOf(Result::class)
        ->and($results[0]->get('body'))
        ->toContain('["Accept-Language"]=&gt;' . PHP_EOL . '  string(35) "de-DE,de;q=0.9,en-US;q=0.8,en;q=0.7"');
});

it('can also use rotating proxies when using the headless browser', function () {
    $crawler = helper_getFastCrawler();

    $crawler
        ->getLoader()
        ->useHeadlessBrowser()
        ->useRotatingProxies([
            'http://localhost:8001',
            'http://localhost:8002',
        ]);

    $crawler
        ->input([
            'http://www.crwlr.software/packages/crawler/v1.1',
            'http://www.crwlr.software/packages/url/v2.0',
            'http://www.crwlr.software/packages/query-string/v1.0',
        ])
        ->addStep(Http::get()->keep(['body']));

    $results = iterator_to_array($crawler->run());

    expect($results)->toHaveCount(3)
        ->and($results[0])
        ->toBeInstanceOf(Result::class)
        ->and($results[0]->get('body'))
        ->toContain('Port: 8001')           // First request with first proxy
        ->and($results[1])
        ->toBeInstanceOf(Result::class)
        ->and($results[1]->get('body'))
        ->toContain('Port: 8002')           // Second request with second proxy
        ->and($results[2])
        ->toBeInstanceOf(Result::class)
        ->and($results[2]->get('body'))
        ->toContain('Port: 8001');          // And finally the third request with the first proxy again.
});
