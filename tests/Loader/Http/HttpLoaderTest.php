<?php

namespace tests\Loader\Http;

use Crwlr\Crawler\Cache\FileCache;
use Crwlr\Crawler\Loader\Http\Messages\RespondedRequest;
use Crwlr\Crawler\Loader\Http\Exceptions\LoadingException;
use Crwlr\Crawler\Loader\Http\HttpLoader;
use Crwlr\Crawler\Loader\Http\Politeness\Throttler;
use Crwlr\Crawler\Steps\Filters\Filter;
use Crwlr\Crawler\UserAgents\BotUserAgent;
use Crwlr\Crawler\UserAgents\UserAgent;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Mockery;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\UriInterface;
use Psr\SimpleCache\CacheInterface;
use Throwable;

use function tests\helper_cachedir;
use function tests\helper_resetCacheDir;

function helper_nonBotUserAgent(): UserAgent
{
    return new UserAgent('Mozilla/5.0 (compatible; FooBot)');
}

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

it('accepts RequestInterface as argument to load', function () {
    $httpClient = Mockery::mock(ClientInterface::class);

    $httpClient->shouldReceive('sendRequest')->twice()->andReturn(new Response());

    $httpLoader = new HttpLoader(helper_nonBotUserAgent(), $httpClient);

    $httpLoader->load(new Request('GET', 'https://www.crwlr.software'));

    $httpLoader->loadOrFail(new Request('GET', 'https://www.crwlr.software'));
});

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

        $httpLoader->beforeLoad(function () use (& $beforeLoadWasCalled) {
            $beforeLoadWasCalled = true;
        });

        $afterLoadWasCalled = false;

        $httpLoader->beforeLoad(function () use (& $afterLoadWasCalled) {
            $afterLoadWasCalled = true;
        });

        $httpLoader->load('https://www.otsch.codes');

        expect($beforeLoadWasCalled)->toBeTrue();

        expect($afterLoadWasCalled)->toBeTrue();
    }
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

    $httpLoader->onSuccess(function () use (& $onSuccessWasCalled) {
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

    $httpLoader->onError(function () use (& $onErrorWasCalled) {
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

it('throws an Exception when request fails in loadOrFail method', function () {
    $httpClient = Mockery::mock(ClientInterface::class);

    $httpClient->shouldReceive('sendRequest')->once()->andReturn(new Response(400));

    $httpLoader = new HttpLoader(helper_nonBotUserAgent(), $httpClient);

    $onErrorWasCalled = false;

    $httpLoader->onError(function () use (& $onErrorWasCalled) {
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
            return $uri->__toString() === '/foo';
        }
    };

    $response = $httpLoader->load('/foo');

    expect($response)->toBeInstanceOf(RespondedRequest::class);

    $response = $httpLoader->load('/bar');

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
    }
);

it('automatically handles redirects', function (string $loadingMethod) {
    $httpClient = Mockery::mock(ClientInterface::class);

    $httpClient->shouldReceive('sendRequest')
        ->twice()
        ->andReturn(
            new Response(301, ['Location' => 'https://www.redirect.com']),
            new Response(200, [], 'YES')
        );

    $httpLoader = new HttpLoader(helper_nonBotUserAgent(), $httpClient);

    $respondedRequest = $httpLoader->{$loadingMethod}('https://www.crwlr.software/packages');

    /** @var RespondedRequest $respondedRequest */
    expect($respondedRequest->requestedUri())->toBe('https://www.crwlr.software/packages');

    expect($respondedRequest->effectiveUri())->toBe('https://www.redirect.com');

    expect($respondedRequest->response->getBody()->getContents())->toBe('YES');
})->with(['load', 'loadOrFail']);

it('calls request start and end tracking methods', function (string $loadingMethod) {
    $httpClient = Mockery::mock(ClientInterface::class);

    $httpClient->shouldReceive('sendRequest')->once()->andReturn(new Response(200));

    $throttler = new class () extends Throttler {
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

    expect($output)->toContain('Track request start https://www.twitter.com');

    expect($output)->toContain('Track request end https://www.twitter.com');
})->with(['load', 'loadOrFail']);

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

    expect($respondedRequest)->toBeInstanceOf(RespondedRequest::class);

    expect($respondedRequest?->request->getMethod())->toBe('GET');

    expect($respondedRequest?->requestedUri())->toBe('https://www.example.com/index');

    expect($respondedRequest?->request->getHeaders())->toHaveKey('foo');

    expect($respondedRequest?->request->getBody()->getContents())->toBe('requestbody');

    expect($respondedRequest?->effectiveUri())->toBe('https://www.example.com/home');

    expect($respondedRequest?->response->getStatusCode())->toBe(201);

    expect($respondedRequest?->response->getHeaders())->toHaveKey('baz');

    expect($respondedRequest?->response->getBody()->getContents())->toBe('responsebody');
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

    $httpLoader->onError(function () use (& $onErrorWasCalled) {
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

        $httpClient->shouldReceive('sendRequest')
            ->twice()
            ->andReturn(new Response(404), new Response(200));

        $cache = new FileCache(helper_cachedir());

        $httpLoader = new HttpLoader(helper_nonBotUserAgent(), $httpClient);

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
    }
)->with(['load', 'loadOrFail']);

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
    }
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
    }
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
    }
)->with(['load', 'loadOrFail']);

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
