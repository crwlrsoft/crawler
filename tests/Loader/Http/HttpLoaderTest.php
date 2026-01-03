<?php

namespace tests\Loader\Http;

use Crwlr\Crawler\Cache\FileCache;
use Crwlr\Crawler\Loader\Http\Cookies\CookieJar;
use Crwlr\Crawler\Loader\Http\Messages\RespondedRequest;
use Crwlr\Crawler\Loader\Http\Exceptions\LoadingException;
use Crwlr\Crawler\Loader\Http\HttpLoader;
use Crwlr\Crawler\Loader\Http\Politeness\Throttler;
use Crwlr\Crawler\Steps\Filters\Filter;
use Crwlr\Crawler\Steps\Loading\Http;
use Crwlr\Crawler\UserAgents\BotUserAgent;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use InvalidArgumentException;
use Mockery;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\UriInterface;
use Psr\SimpleCache\CacheInterface;
use tests\_Stubs\DummyLogger;
use tests\_Stubs\RespondedRequestChild;
use Throwable;

use function tests\helper_cachedir;
use function tests\helper_getFastLoader;
use function tests\helper_nonBotUserAgent;
use function tests\helper_resetCacheDir;

afterEach(function () {
    helper_resetCacheDir();
});

/** @var TestCase $this */

it('accepts url string as argument to load', function () {
    $httpClient = Mockery::mock(ClientInterface::class);

    $httpClient->shouldReceive('sendRequest')->twice()->andReturn(new Response());

    $httpLoader = new HttpLoader(helper_nonBotUserAgent(), $httpClient);

    $httpLoader->load('https://www.crwlr.software');

    $httpLoader->loadOrFail('https://www.crwlr.software');
});

it('fails and logs an error when invoked with a relative reference URI', function () {
    $logger = new DummyLogger();

    $httpLoader = new HttpLoader(helper_nonBotUserAgent(), logger: $logger);

    $httpLoader->load('/foo');

    expect($logger->messages)->not->toBeEmpty()
        ->and($logger->messages[0]['message'])->toBe(
            'Invalid input URL: /foo - The URI is a relative reference and therefore can\'t be loaded.',
        );
});

it('fails and throws an exception when loadOrFail() is called with a relative reference URI', function () {
    $logger = new DummyLogger();

    $httpLoader = new HttpLoader(helper_nonBotUserAgent(), logger: $logger);

    $httpLoader->loadOrFail('/foo');
})->throws(InvalidArgumentException::class);

it('accepts RequestInterface as argument to load', function () {
    $httpClient = Mockery::mock(ClientInterface::class);

    $httpClient->shouldReceive('sendRequest')->twice()->andReturn(new Response());

    $httpLoader = new HttpLoader(helper_nonBotUserAgent(), $httpClient);

    $httpLoader->load(new Request('GET', 'https://www.crwlr.software'));

    $httpLoader->loadOrFail(new Request('GET', 'https://www.crwlr.software'));
});

it('fails and logs an error when invoked with a RequestInterface object having a relative reference URI', function () {
    $logger = new DummyLogger();

    $httpLoader = new HttpLoader(helper_nonBotUserAgent(), logger: $logger);

    $httpLoader->load(new Request('GET', '/foo'));

    expect($logger->messages)->not->toBeEmpty()
        ->and($logger->messages[0]['message'])->toBe(
            'Invalid input URL: /foo - The URI is a relative reference and therefore can\'t be loaded.',
        );
});

it(
    'fails and throws an exception when loadOrFail() is called with a RequestInterface object having a relative ' .
    'reference URI',
    function () {
        $logger = new DummyLogger();

        $httpLoader = new HttpLoader(helper_nonBotUserAgent(), logger: $logger);

        $httpLoader->loadOrFail(new Request('GET', '/foo'));
    },
)->throws(InvalidArgumentException::class);

it(
    'calls the before and after load hooks regardless whether the response was successful or not',
    function ($responseStatusCode) {
        $httpClient = Mockery::mock(ClientInterface::class);

        if ($responseStatusCode === 300) {
            $httpClient->shouldReceive('sendRequest')
                ->twice()
                ->andReturn(new Response($responseStatusCode), new Response(200));
        } else {
            $httpClient->shouldReceive('sendRequest')->once()->andReturn(new Response($responseStatusCode));
        }

        $httpLoader = new HttpLoader(helper_nonBotUserAgent(), $httpClient);

        $beforeLoadWasCalled = false;

        $httpLoader->beforeLoad(function () use (&$beforeLoadWasCalled) {
            $beforeLoadWasCalled = true;
        });

        $afterLoadWasCalled = false;

        $httpLoader->afterLoad(function () use (&$afterLoadWasCalled) {
            $afterLoadWasCalled = true;
        });

        $httpLoader->load('https://www.otsch.codes');

        expect($beforeLoadWasCalled)->toBeTrue()
            ->and($afterLoadWasCalled)->toBeTrue();
    },
)->with([
    [100],
    [200],
    [300],
    [400],
    [500],
]);

it('calls the onSuccess hook on a successful response', function ($responseStatusCode) {
    $httpClient = Mockery::mock(ClientInterface::class);

    $httpClient->shouldReceive('sendRequest')->twice()->andReturn(new Response($responseStatusCode));

    $httpLoader = new HttpLoader(helper_nonBotUserAgent(), $httpClient);

    $onSuccessWasCalled = false;

    $httpLoader->onSuccess(function () use (&$onSuccessWasCalled) {
        $onSuccessWasCalled = true;
    });

    $httpLoader->load('https://www.otsch.codes');

    expect($onSuccessWasCalled)->toBeTrue();

    $onSuccessWasCalled = false;

    $httpLoader->loadOrFail('https://www.otsch.codes');

    expect($onSuccessWasCalled)->toBeTrue();
})->with([
    [200],
    [201],
    [202],
]);

it('calls the onError hook on a failed request', function ($responseStatusCode) {
    $httpClient = Mockery::mock(ClientInterface::class);

    $httpClient->shouldReceive('sendRequest')->once()->andReturn(new Response($responseStatusCode));

    $httpLoader = new HttpLoader(helper_nonBotUserAgent(), $httpClient);

    $onErrorWasCalled = false;

    $httpLoader->onError(function () use (&$onErrorWasCalled) {
        $onErrorWasCalled = true;
    });

    $httpLoader->load('https://www.otsch.codes');

    expect($onErrorWasCalled)->toBeTrue();
})->with([
    [400],
    [404],
    [422],
    [500],
]);

it('calls the onCacheHit hook when a response for the request was found in the cache', function (string $loadMethod) {
    $cache = new FileCache(helper_cachedir());

    $userAgent = helper_nonBotUserAgent();

    $respondedRequest = new RespondedRequest(
        new Request(
            'GET',
            'https://www.example.com/foo',
            ['Host' => ['www.example.com'], 'User-Agent' => [(string) $userAgent]],
        ),
        new Response(body: 'Hello World!'),
    );

    $cache->set($respondedRequest->cacheKey(), $respondedRequest);

    $httpLoader = new HttpLoader($userAgent);

    $httpLoader->setCache($cache);

    $onCacheHitWasCalled = false;

    $httpLoader->onCacheHit(function () use (&$onCacheHitWasCalled) {
        $onCacheHitWasCalled = true;
    });

    $response = $httpLoader->{$loadMethod}('https://www.example.com/foo');

    /** @var RespondedRequest $response */

    expect($onCacheHitWasCalled)->toBeTrue()
        ->and($response->isServedFromCache())->toBeTrue();
})->with(['load', 'loadOrFail']);

it('throws an Exception when request fails in loadOrFail method', function () {
    $httpClient = Mockery::mock(ClientInterface::class);

    $httpClient->shouldReceive('sendRequest')->once()->andReturn(new Response(400));

    $httpLoader = new HttpLoader(helper_nonBotUserAgent(), $httpClient);

    $onErrorWasCalled = false;

    $httpLoader->onError(function () use (&$onErrorWasCalled) {
        $onErrorWasCalled = true;
    });

    try {
        $httpLoader->loadOrFail('https://www.otsch.codes');
    } catch (LoadingException $exception) {
        expect($exception)->toBeInstanceOf(LoadingException::class);
    }

    expect($onErrorWasCalled)->toBeFalse();
});

test('You can implement logic to disallow certain request', function () {
    $httpClient = Mockery::mock(ClientInterface::class);

    $httpClient->shouldReceive('sendRequest')->once()->andReturn(new Response());

    $httpLoader = new class (new BotUserAgent('Foo'), $httpClient) extends HttpLoader {
        public function isAllowedToBeLoaded(UriInterface $uri, bool $throwsException = false): bool
        {
            return $uri->__toString() === 'https://www.example.com/foo';
        }
    };

    $response = $httpLoader->load('https://www.example.com/foo');

    expect($response)->toBeInstanceOf(RespondedRequest::class);

    $response = $httpLoader->load('https://www.example.com/bar');

    expect($response)->toBeNull();
});

test(
    'The isAllowedToBeLoaded method is called with argument throwsException true when called from loadOrFail',
    function () {
        $httpClient = Mockery::mock(ClientInterface::class);

        $httpClient->shouldReceive('sendRequest')->once()->andReturn(new Response());

        $httpLoader = new class (new BotUserAgent('Foo'), $httpClient) extends HttpLoader {
            public function isAllowedToBeLoaded(UriInterface $uri, bool $throwsException = false): bool
            {
                if ($throwsException) {
                    throw new LoadingException('Fail to load ' . $uri->__toString());
                }

                return $uri->__toString() === 'https://www.example.com';
            }
        };

        $httpLoader->load('https://www.example.com');

        try {
            $httpLoader->loadOrFail('https://www.example.com');
        } catch (LoadingException $exception) {
            expect($exception)->toBeInstanceOf(LoadingException::class);
        }
    },
);

it('automatically handles redirects', function (string $loadingMethod) {
    $httpClient = Mockery::mock(ClientInterface::class);

    $httpClient->shouldReceive('sendRequest')
        ->twice()
        ->andReturn(
            new Response(301, ['Location' => 'https://www.redirect.com']),
            new Response(200, [], 'YES'),
        );

    $httpLoader = new HttpLoader(helper_nonBotUserAgent(), $httpClient);

    $respondedRequest = $httpLoader->{$loadingMethod}('https://www.crwlr.software/packages');

    /** @var RespondedRequest $respondedRequest */
    expect($respondedRequest->requestedUri())->toBe('https://www.crwlr.software/packages')
        ->and($respondedRequest->effectiveUri())->toBe('https://www.redirect.com')
        ->and($respondedRequest->response->getBody()->getContents())->toBe('YES');
})->with(['load', 'loadOrFail']);

it('calls request start and end tracking methods', function (string $loadingMethod) {
    $httpClient = Mockery::mock(ClientInterface::class);

    $httpClient->shouldReceive('sendRequest')->once()->andReturn(new Response(200));

    $throttler = new class extends Throttler {
        public function trackRequestStartFor(UriInterface $url): void
        {
            echo 'Track request start ' . $url . PHP_EOL;

            parent::trackRequestStartFor($url);
        }

        public function trackRequestEndFor(UriInterface $url): void
        {
            echo 'Track request end ' . $url . PHP_EOL;

            parent::trackRequestEndFor($url);
        }
    };

    $httpLoader = new HttpLoader(helper_nonBotUserAgent(), $httpClient, throttler: $throttler);

    $httpLoader->{$loadingMethod}('https://www.twitter.com');

    $output = $this->getActualOutputForAssertion();

    expect($output)->toContain('Track request start https://www.twitter.com')
        ->and($output)->toContain('Track request end https://www.twitter.com');
})->with(['load', 'loadOrFail']);

it(
    'calls trackRequestEndFor only once and with the original request URL when there is a redirect',
    function (string $loadingMethod) {
        $httpClient = Mockery::mock(ClientInterface::class);

        $httpClient
            ->shouldReceive('sendRequest')
            ->once()
            ->withArgs(function (Request $request) {
                return (string) $request->getUri() === 'https://www.example.com/foo';
            })
            ->andReturn(new Response(301, ['Location' => 'https://www.example.com/bar']));

        $httpClient
            ->shouldReceive('sendRequest')
            ->once()
            ->withArgs(function (Request $request) {
                return (string) $request->getUri() === 'https://www.example.com/bar';
            })
            ->andReturn(new Response(200));

        $throttler = new class extends Throttler {
            public function trackRequestEndFor(UriInterface $url): void
            {
                echo 'Track request end ' . $url . PHP_EOL;

                parent::trackRequestEndFor($url);
            }
        };

        $httpLoader = new HttpLoader(helper_nonBotUserAgent(), $httpClient, throttler: $throttler);

        $httpLoader->{$loadingMethod}('https://www.example.com/foo');

        $output = $this->getActualOutputForAssertion();

        expect($output)->toContain('Track request end https://www.example.com/foo')
            ->and(count(explode('Track request end', $output)))->toBe(2);
    },
)->with(['load', 'loadOrFail']);

it('automatically logs loading success message', function ($loadingMethod) {
    $httpClient = Mockery::mock(ClientInterface::class);

    $httpClient->shouldReceive('sendRequest')->once()->andReturn(new Response());

    $httpLoader = new HttpLoader(helper_nonBotUserAgent(), $httpClient);

    $httpLoader->{$loadingMethod}(new Request('GET', 'https://phpstan.org/'));

    $output = $this->getActualOutputForAssertion();

    expect($output)->toContain('Loaded https://phpstan.org/');
})->with(['load', 'loadOrFail']);

it('automatically logs loading error message in normal load method', function () {
    $httpClient = Mockery::mock(ClientInterface::class);

    $httpClient->shouldReceive('sendRequest')->once()->andReturn(new Response(500));

    $httpLoader = new HttpLoader(helper_nonBotUserAgent(), $httpClient);

    $httpLoader->load(new Request('GET', 'https://phpstan.org/'));

    $output = $this->getActualOutputForAssertion();

    expect($output)->toContain('Failed to load https://phpstan.org/');
});

it('automatically adds the User-Agent header before sending', function () {
    $httpClient = Mockery::mock(ClientInterface::class);

    $httpClient->shouldReceive('sendRequest')
        ->once()
        ->withArgs(function ($request) {
            return str_contains($request->getHeaderLine('User-Agent'), 'FooBot');
        })
        ->andReturn(new Response());

    $httpLoader = new HttpLoader(helper_nonBotUserAgent(), $httpClient);

    $httpLoader->load('https://www.facebook.com');
});

it('tries to get responses from cache', function () {
    $httpClient = Mockery::mock(ClientInterface::class);

    $httpClient->shouldNotReceive('sendRequest');

    $cache = Mockery::mock(CacheInterface::class);

    $cache->shouldReceive('has')->once()->andReturn(true);

    $cache->shouldReceive('get')
        ->once()
        ->andReturn(new RespondedRequest(new Request('GET', '/'), new Response()));

    $httpLoader = new HttpLoader(helper_nonBotUserAgent(), $httpClient);

    $httpLoader->setCache($cache);

    $httpLoader->load('https://www.facebook.com');
});

test(
    'when a response is served from cache, the RespondedRequest::isServedFromCache() method returns true,',
    function (string $loadMethod) {
        $cache = new FileCache(helper_cachedir());

        $userAgent = helper_nonBotUserAgent();

        $respondedRequest = new RespondedRequest(
            new Request(
                'GET',
                'https://www.example.com/bar',
                ['Host' => ['www.example.com'], 'User-Agent' => [(string) $userAgent]],
            ),
            new Response(body: 'Hi!'),
        );

        $cache->set($respondedRequest->cacheKey(), $respondedRequest);

        $clientMock = Mockery::mock(Client::class);

        $clientMock
            ->shouldReceive('sendRequest')
            ->once()
            ->withArgs(function (Request $request) {
                return (string) $request->getUri() === 'https://www.example.com/foo';
            })
            ->andReturn(new Response(body: 'Hi!'));

        $httpLoader = (new HttpLoader($userAgent, $clientMock))->setCache($cache);

        $response = $httpLoader->{$loadMethod}('https://www.example.com/foo');

        /** @var RespondedRequest $response */

        expect($response->isServedFromCache())->toBeFalse();

        $response = $httpLoader->{$loadMethod}('https://www.example.com/bar');

        /** @var RespondedRequest $response */

        expect($response->isServedFromCache())->toBeTrue();
    },
)->with(['load', 'loadOrFail']);

it(
    'does not serve a request from the cache, when skipCacheForNextRequest() was called',
    function (string $loadMethod) {
        $cache = new FileCache(helper_cachedir());

        $userAgent = helper_nonBotUserAgent();

        $respondedRequest = new RespondedRequest(
            new Request(
                'GET',
                'https://www.example.com/blog/posts',
                ['Host' => ['www.example.com'], 'User-Agent' => [(string) $userAgent]],
            ),
            new Response(body: 'previously cached blog posts'),
        );

        $cache->set($respondedRequest->cacheKey(), $respondedRequest);

        $clientMock = Mockery::mock(Client::class);

        $clientMock
            ->shouldReceive('sendRequest')
            ->once()
            ->withArgs(function (Request $request) {
                return (string) $request->getUri() === 'https://www.example.com/blog/posts';
            })
            ->andReturn(new Response(body: 'loaded blog posts'));

        $httpLoader = (new HttpLoader($userAgent, $clientMock))
            ->setCache($cache)
            ->skipCacheForNextRequest();

        $response = $httpLoader->{$loadMethod}('https://www.example.com/blog/posts');

        /** @var RespondedRequest $response */

        expect($response->isServedFromCache())->toBeFalse()
            ->and(Http::getBodyString($response))->toBe('loaded blog posts');

        // Skipping the cache is only effective for loading. It still adds the loaded response to the cache.
        // So on the next request, when not again calling the skip cache method, the cache will return that
        // previously loaded response.
        $response = $httpLoader->{$loadMethod}('https://www.example.com/blog/posts');

        expect($response->isServedFromCache())->toBeTrue()
            ->and(Http::getBodyString($response))->toBe('loaded blog posts');
    },
)->with(['load', 'loadOrFail']);

it('still handles legacy (until v0.7) cached responses', function () {
    $httpClient = Mockery::mock(ClientInterface::class);

    $httpClient->shouldNotReceive('sendRequest');

    $cache = Mockery::mock(CacheInterface::class);

    $cache->shouldReceive('has')->once()->andReturn(true);

    $cache->shouldReceive('get')
        ->once()
        ->andReturn([
            'requestMethod' => 'GET',
            'requestUri' => 'https://www.example.com/index',
            'requestHeaders' => ['foo' => ['bar']],
            'requestBody' => 'requestbody',
            'effectiveUri' => 'https://www.example.com/home',
            'responseStatusCode' => 201,
            'responseHeaders' => ['baz' => ['quz']],
            'responseBody' => 'responsebody',
        ]);

    $httpLoader = new HttpLoader(helper_nonBotUserAgent(), $httpClient);

    $httpLoader->setCache($cache);

    $respondedRequest = $httpLoader->load('https://www.example.com/index');

    expect($respondedRequest)->toBeInstanceOf(RespondedRequest::class)
        ->and($respondedRequest?->request->getMethod())->toBe('GET')
        ->and($respondedRequest?->requestedUri())->toBe('https://www.example.com/index')
        ->and($respondedRequest?->request->getHeaders())->toHaveKey('foo')
        ->and($respondedRequest?->request->getBody()->getContents())->toBe('requestbody')
        ->and($respondedRequest?->effectiveUri())->toBe('https://www.example.com/home')
        ->and($respondedRequest?->response->getStatusCode())->toBe(201)
        ->and($respondedRequest?->response->getHeaders())->toHaveKey('baz')
        ->and($respondedRequest?->response->getBody()->getContents())->toBe('responsebody');
});

it('fails when it gets a failed response from cache', function () {
    $httpClient = Mockery::mock(ClientInterface::class);

    $cache = Mockery::mock(CacheInterface::class);

    $cache->shouldReceive('has')->once()->andReturn(true);

    $cache->shouldReceive('get')
        ->once()
        ->andReturn(new RespondedRequest(new Request('GET', '/'), new Response(404)));

    $httpLoader = new HttpLoader(helper_nonBotUserAgent(), $httpClient);

    $httpLoader->setCache($cache);

    $onErrorWasCalled = false;

    $httpLoader->onError(function () use (&$onErrorWasCalled) {
        $onErrorWasCalled = true;
    });

    $httpLoader->load('https://www.facebook.com');

    expect($onErrorWasCalled)->toBeTrue();
});

it('fails when it gets a failed response from cache in loadOrFail', function () {
    $httpClient = Mockery::mock(ClientInterface::class);

    $cache = Mockery::mock(CacheInterface::class);

    $cache->shouldReceive('has')->once()->andReturn(true);

    $cache->shouldReceive('get')
        ->once()
        ->andReturn(new RespondedRequest(new Request('GET', 'facebook'), new Response(404)));

    $httpLoader = new HttpLoader(helper_nonBotUserAgent(), $httpClient);

    $httpLoader->setCache($cache);

    $httpLoader->loadOrFail('https://www.facebook.com');
})->throws(LoadingException::class);

it('adds loaded responses to the cache when it has a cache', function ($loadingMethod) {
    $httpClient = Mockery::mock(ClientInterface::class);

    $httpClient->shouldReceive('sendRequest')->once()->andReturn(new Response());

    $cache = Mockery::mock(CacheInterface::class);

    $cache->shouldReceive('has')->once()->andReturn(false);

    $cache->shouldReceive('set')->once();

    $httpLoader = new HttpLoader(helper_nonBotUserAgent(), $httpClient);

    $httpLoader->setCache($cache);

    $httpLoader->{$loadingMethod}('https://laravel.com/');
})->with(['load', 'loadOrFail']);

test(
    'when a cached response was an error response it retries to load it when retryCachedErrorResponses() was called',
    function (string $loadingMethod) {
        $httpClient = Mockery::mock(ClientInterface::class);

        $httpClient
            ->shouldReceive('sendRequest')
            ->twice()
            ->andReturn(new Response(404), new Response(200));

        $cache = new FileCache(helper_cachedir());

        $httpLoader = helper_getFastLoader(httpClient: $httpClient);

        $httpLoader->setCache($cache);

        $httpLoader->retryCachedErrorResponses();

        try {
            $httpLoader->{$loadingMethod}('https://www.example.com/articles/123');
        } catch (Throwable $exception) {
        }

        try {
            $httpLoader->{$loadingMethod}('https://www.example.com/articles/123');
        } catch (Throwable $exception) {
        }
    },
)->with(['load', 'loadOrFail']);

test('retrying cached error responses can be restricted to only certain response status codes', function () {
    $httpClient = Mockery::mock(ClientInterface::class);

    $httpClient
        ->shouldReceive('sendRequest')
        ->twice()
        ->andReturn(new Response(404), new Response(400));

    $cache = new FileCache(helper_cachedir());

    $httpLoader = helper_getFastLoader(httpClient: $httpClient);

    $httpLoader->setCache($cache);

    $httpLoader
        ->retryCachedErrorResponses()
        ->only([404, 503]);

    $respondedRequest = $httpLoader->load('https://www.example.com/foo');

    expect($respondedRequest?->response->getStatusCode())->toBe(404);

    $respondedRequest = $httpLoader->load('https://www.example.com/foo');

    expect($respondedRequest?->response->getStatusCode())->toBe(400);

    $respondedRequest = $httpLoader->load('https://www.example.com/foo');

    expect($respondedRequest?->response->getStatusCode())->toBe(400);
});

test('certain error status codes can be excluded from being retried', function () {
    $httpClient = Mockery::mock(ClientInterface::class);

    $httpClient
        ->shouldReceive('sendRequest')
        ->twice()
        ->andReturn(new Response(404), new Response(500));

    $cache = new FileCache(helper_cachedir());

    $httpLoader = helper_getFastLoader(httpClient: $httpClient);

    $httpLoader->setCache($cache);

    $httpLoader
        ->retryCachedErrorResponses()
        ->except([410, 500]);

    $respondedRequest = $httpLoader->load('https://www.example.com/foo');

    expect($respondedRequest?->response->getStatusCode())->toBe(404);

    $respondedRequest = $httpLoader->load('https://www.example.com/foo');

    expect($respondedRequest?->response->getStatusCode())->toBe(500);

    $respondedRequest = $httpLoader->load('https://www.example.com/foo');

    expect($respondedRequest?->response->getStatusCode())->toBe(500);
});

it(
    'adds responses to the cache but doesn\'t try to get them from the cache, when writeOnlyCache() was called',
    function ($loadingMethod) {
        $httpClient = Mockery::mock(ClientInterface::class);

        $httpClient->shouldReceive('sendRequest')->twice()->andReturn(new Response());

        $cache = new FileCache(helper_cachedir());

        $httpLoader = new HttpLoader(helper_nonBotUserAgent(), $httpClient);

        $httpLoader->setCache($cache);

        $httpLoader->writeOnlyCache();

        try {
            $httpLoader->{$loadingMethod}('https://www.example.com/articles/123');
        } catch (Throwable $exception) {
        }

        try {
            $httpLoader->{$loadingMethod}('https://www.example.com/articles/123');
        } catch (Throwable $exception) {
        }
    },
)->with(['load', 'loadOrFail']);

test(
    'When cache filters are defined via the cacheOnlyWhereUrl() method it caches only responses for matching URLs',
    function (string $loadingMethod) {
        $httpClient = Mockery::mock(ClientInterface::class);

        $httpClient
            ->shouldReceive('sendRequest')
            ->twice()
            ->andReturnUsing(function (Request $request) {
                return new Response(200, body: $request->getUri() . ' response');
            });

        $cache = new FileCache(helper_cachedir());

        $httpLoader = new HttpLoader(helper_nonBotUserAgent(), $httpClient);

        $httpLoader->setCache($cache);

        $httpLoader->cacheOnlyWhereUrl(Filter::urlPathStartsWith('/bar/'));

        $respondedRequest = $httpLoader->{$loadingMethod}('https://www.example.com/foo/something');

        expect($cache->get($respondedRequest->cacheKey()))->toBeNull();

        $respondedRequest = $httpLoader->{$loadingMethod}('https://www.example.com/bar/something');

        expect($cache->get($respondedRequest->cacheKey()))->toBeInstanceOf(RespondedRequest::class);
    },
)->with(['load', 'loadOrFail']);

test(
    'When multiple cache filters are defined via the cacheOnlyWhereUrl() method, all of them are used',
    function (string $loadingMethod) {
        $httpClient = Mockery::mock(ClientInterface::class);

        $httpClient
            ->shouldReceive('sendRequest')
            ->times(3)
            ->andReturnUsing(function (Request $request) {
                return new Response(200, body: $request->getUri() . ' response');
            });

        $cache = new FileCache(helper_cachedir());

        $httpLoader = new HttpLoader(helper_nonBotUserAgent(), $httpClient);

        $httpLoader->setCache($cache);

        $httpLoader
            ->cacheOnlyWhereUrl(Filter::urlPathStartsWith('/bar/'))
            ->cacheOnlyWhereUrl(Filter::urlHost('www.example.com'));

        $respondedRequest = $httpLoader->{$loadingMethod}('https://www.example.com/foo/something');

        expect($cache->get($respondedRequest->cacheKey()))->toBeNull();

        $respondedRequest = $httpLoader->{$loadingMethod}('https://www.crwlr.software/bar/something');

        expect($cache->get($respondedRequest->cacheKey()))->toBeNull();

        $respondedRequest = $httpLoader->{$loadingMethod}('https://www.example.com/bar/something');

        expect($cache->get($respondedRequest->cacheKey()))->toBeInstanceOf(RespondedRequest::class);
    },
)->with(['load', 'loadOrFail']);

test(
    'when a request was redirected, only one of the URLs has to match the filters defined via cacheOnlyWhereUrl()',
    function (string $loadingMethod) {
        $httpClient = Mockery::mock(ClientInterface::class);

        $httpClient
            ->shouldReceive('sendRequest')
            ->andReturnUsing(function (Request $request) {
                $url = (string) $request->getUri();

                $redirectUrl = null;

                if ($url === 'https://www.example.com/foo/something') {
                    $redirectUrl = 'https://www.example.com/bar/something';
                } elseif ($url === 'https://www.example.com/bar/something') {
                    $redirectUrl = 'https://www.example.com/baz/something';
                }

                if ($redirectUrl) {
                    return new Response(301, ['Location' => $redirectUrl]);
                }

                return new Response(200, body: $request->getUri() . ' response');
            });

        $cache = new FileCache(helper_cachedir());

        $httpLoader = new HttpLoader(helper_nonBotUserAgent(), $httpClient);

        $httpLoader->setCache($cache);

        $httpLoader->cacheOnlyWhereUrl(Filter::urlPathStartsWith('/bar/'));

        $respondedRequest = $httpLoader->{$loadingMethod}('https://www.example.com/foo/something');

        expect($cache->get($respondedRequest->cacheKey()))->toBeInstanceOf(RespondedRequest::class);

        $cache->clear();

        $respondedRequest = $httpLoader->{$loadingMethod}('https://www.example.com/bar/something');

        expect($cache->get($respondedRequest->cacheKey()))->toBeInstanceOf(RespondedRequest::class);

        $cache->clear();

        $respondedRequest = $httpLoader->{$loadingMethod}('https://www.example.com/baz/something');

        expect($cache->get($respondedRequest->cacheKey()))->toBeNull();
    },
)->with(['load', 'loadOrFail']);

it('uses the cache only for requests that meet the filter criteria', function (string $loadingMethod) {
    $httpClient = Mockery::mock(ClientInterface::class);

    $httpClient
        ->shouldReceive('sendRequest')
        ->once()
        ->andReturnUsing(function (Request $request) {
            return new Response(200, body: $request->getUri() . ' response');
        });

    $userAgent = helper_nonBotUserAgent();

    $cache = new FileCache(helper_cachedir());

    $cachedResponse = new RespondedRequest(
        new Request('GET', 'https://www.example.com/foo/test', headers: ['User-Agent' => $userAgent->__toString()]),
        new Response(),
    );

    $cache->set($cachedResponse->cacheKey(), $cachedResponse);

    $cachedResponse = new RespondedRequest(
        new Request('GET', 'https://www.example.com/bar/test', headers: ['User-Agent' => $userAgent->__toString()]),
        new Response(),
    );

    $cache->set($cachedResponse->cacheKey(), $cachedResponse);

    $httpLoader = new HttpLoader($userAgent, $httpClient);

    $httpLoader->setCache($cache);

    $httpLoader->cacheOnlyWhereUrl(Filter::urlPathStartsWith('/bar/'));

    $httpLoader->{$loadingMethod}('https://www.example.com/foo/test');

    $httpLoader->{$loadingMethod}('https://www.example.com/bar/test');
})->with(['load', 'loadOrFail']);

it('updates an existing cached response', function () {
    $httpClient = Mockery::mock(ClientInterface::class);

    $httpClient
        ->shouldReceive('sendRequest')
        ->once()
        ->andReturn(new Response(body: 'hello'));

    $cache = new FileCache(helper_cachedir());

    $cache->clear();

    $httpLoader = new HttpLoader(helper_nonBotUserAgent(), $httpClient);

    $httpLoader->setCache($cache);

    $response = $httpLoader->load('https://www.example.com/idontknow');

    if (!$response) {
        throw new Exception('failed to get response');
    }

    $extendedResponse = RespondedRequestChild::fromRespondedRequest($response);

    $httpLoader->addToCache($extendedResponse);

    $response = $httpLoader->load('https://www.example.com/idontknow');

    /** @var RespondedRequestChild $response */

    expect($response)->toBeInstanceOf(RespondedRequestChild::class)
        ->and($response->itseme())->toBe('mario');
});

it('does not add cookies to the cookie jar when a response was served from the cache', function () {
    $httpClient = Mockery::mock(ClientInterface::class);

    $httpClient->shouldNotReceive('sendRequest');

    $cache = new FileCache(helper_cachedir());

    $httpLoader = new HttpLoader(helper_nonBotUserAgent(), $httpClient);

    $httpLoader->setCache($cache);

    $respondedRequest = new RespondedRequest(
        new Request(
            'GET',
            'https://www.example.com/wtf',
            ['Host' => ['www.example.com'], 'User-Agent' => [(string) helper_nonBotUserAgent()]],
        ),
        new Response(headers: ['Set-Cookie' => 'foo=bar'], body: 'Wtf!'),
    );

    $cache->set($respondedRequest->cacheKey(), $respondedRequest);

    $httpLoader->load('https://www.example.com/wtf');

    $cookieJar = invade($httpLoader)->cookieJar;

    /** @var CookieJar $cookieJar */

    $cookies = $cookieJar->allByDomain('example.com');

    expect($cookies)->toHaveCount(0);
});

test('By default it uses the cookie jar and passes on cookies', function () {
    $httpClient = Mockery::mock(ClientInterface::class);

    $httpClient->shouldReceive('sendRequest')->withArgs(function (RequestInterface $request) {
        return $request->getUri()->__toString() === 'https://www.crwlr.software/';
    })->andReturn(new Response(200, ['Set-Cookie' => ['cookie1=foo']]));

    $httpClient->shouldReceive('sendRequest')->withArgs(function (RequestInterface $request) {
        $cookiesHeader = $request->getHeader('Cookie');

        return $request->getUri()->__toString() === 'https://www.crwlr.software/blog' &&
            $cookiesHeader === ['cookie1=foo'];
    })->andReturn(new Response(200, ['Set-Cookie' => ['cookie1=foo', 'cookie2=bar']]));

    $httpClient->shouldReceive('sendRequest')->withArgs(function (RequestInterface $request) {
        $cookiesHeader = $request->getHeader('Cookie');

        return $request->getUri()->__toString() === 'https://www.crwlr.software/contact' &&
            $cookiesHeader === ['cookie1=foo', 'cookie2=bar'];
    })->andReturn(new Response(200, ['Set-Cookie' => ['cookie1=foo2', 'cookie2=bar2', 'cookie3=baz']]));

    $httpClient->shouldReceive('sendRequest')->withArgs(function (RequestInterface $request) {
        $cookiesHeader = $request->getHeader('Cookie');

        return $request->getUri()->__toString() === 'https://www.crwlr.software/packages' &&
            $cookiesHeader === ['cookie1=foo2', 'cookie2=bar2', 'cookie3=baz'];
    })->andReturn(new Response());

    $httpLoader = new HttpLoader(helper_nonBotUserAgent(), $httpClient);

    $httpLoader->load('https://www.crwlr.software/');

    $httpLoader->load('https://www.crwlr.software/blog');

    $httpLoader->loadOrFail('https://www.crwlr.software/contact');

    $httpLoader->loadOrFail('https://www.crwlr.software/packages');

    expect(true)->toBeTrue(); // Just here so pest doesn't complain that there is no assertion.
});

test('You can turn off using the cookie jar', function () {
    $httpClient = Mockery::mock(ClientInterface::class);

    $httpClient->shouldReceive('sendRequest')->withArgs(function (RequestInterface $request) {
        return $request->getUri()->__toString() === 'https://www.crwlr.software/';
    })->andReturn(new Response(200, ['Set-Cookie' => ['cookie1=foo']]));

    $httpClient->shouldReceive('sendRequest')->withArgs(function (RequestInterface $request) {
        $cookiesHeader = $request->getHeader('Cookie');

        return $request->getUri()->__toString() === 'https://www.crwlr.software/blog' && $cookiesHeader === [];
    })->andReturn(new Response(200, ['Set-Cookie' => ['cookie1=foo', 'cookie2=bar']]));

    $httpClient->shouldReceive('sendRequest')->withArgs(function (RequestInterface $request) {
        $cookiesHeader = $request->getHeader('Cookie');

        return $request->getUri()->__toString() === 'https://www.crwlr.software/contact' && $cookiesHeader === [];
    })->andReturn(new Response(200, ['Set-Cookie' => ['cookie1=foo2', 'cookie2=bar2', 'cookie3=baz']]));

    $httpClient->shouldReceive('sendRequest')->withArgs(function (RequestInterface $request) {
        $cookiesHeader = $request->getHeader('Cookie');

        return $request->getUri()->__toString() === 'https://www.crwlr.software/packages' && $cookiesHeader === [];
    })->andReturn(new Response());

    $httpLoader = new HttpLoader(helper_nonBotUserAgent(), $httpClient);

    $httpLoader->dontUseCookies();

    $httpLoader->load('https://www.crwlr.software/');

    $httpLoader->load('https://www.crwlr.software/blog');

    $httpLoader->loadOrFail('https://www.crwlr.software/contact');

    $httpLoader->loadOrFail('https://www.crwlr.software/packages');

    expect(true)->toBeTrue(); // Just here so pest doesn't complain that there is no assertion.
});
