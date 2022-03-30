<?php

namespace tests\Loader\Http;

use Crwlr\Crawler\Loader\Http\Messages\RespondedRequest;
use Crwlr\Crawler\Cache\HttpResponseCacheItem;
use Crwlr\Crawler\Exceptions\LoadingException;
use Crwlr\Crawler\Loader\Http\HttpLoader;
use Crwlr\Crawler\UserAgents\BotUserAgent;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Mockery;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\UriInterface;
use Psr\SimpleCache\CacheInterface;
use stdClass;

/** @var TestCase $this */

test('It accepts url string as argument to load', function () {
    $httpClient = Mockery::mock(ClientInterface::class);
    $httpClient->shouldReceive('sendRequest')->twice()->andReturn(new Response());
    $httpLoader = new HttpLoader(new BotUserAgent('Foo'), $httpClient);
    $httpLoader->load('https://www.crwlr.software');
    $httpLoader->loadOrFail('https://www.crwlr.software');
});

test('It accepts RequestInterface as argument to load', function () {
    $httpClient = Mockery::mock(ClientInterface::class);
    $httpClient->shouldReceive('sendRequest')->twice()->andReturn(new Response());
    $httpLoader = new HttpLoader(new BotUserAgent('Foo'), $httpClient);
    $httpLoader->load(new Request('GET', 'https://www.crwlr.software'));
    $httpLoader->loadOrFail(new Request('GET', 'https://www.crwlr.software'));
});

test('It does not accept other argument types for the load method', function ($loadMethod) {
    $httpClient = Mockery::mock(ClientInterface::class);
    $httpLoader = new HttpLoader(new BotUserAgent('Foo'), $httpClient);
    $httpLoader->{$loadMethod}(new stdClass());
})->with(['load', 'loadOrFail'])->expectError();

test(
    'It calls the before and after load hooks regardless whether the response was successful or not',
    function ($responseStatusCode) {
        $httpClient = Mockery::mock(ClientInterface::class);

        if ($responseStatusCode === 300) {
            $httpClient->shouldReceive('sendRequest')
                ->twice()
                ->andReturn(new Response($responseStatusCode), new Response(200));
        } else {
            $httpClient->shouldReceive('sendRequest')->once()->andReturn(new Response($responseStatusCode));
        }

        $httpLoader = new HttpLoader(new BotUserAgent('Foo'), $httpClient);
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
)->with([100, 200, 300, 400, 500]);

test('It calls the onSuccess hook on a successful response', function ($responseStatusCode) {
    $httpClient = Mockery::mock(ClientInterface::class);
    $httpClient->shouldReceive('sendRequest')->twice()->andReturn(new Response($responseStatusCode));
    $httpLoader = new HttpLoader(new BotUserAgent('Foo'), $httpClient);
    $onSuccessWasCalled = false;
    $httpLoader->onSuccess(function () use (& $onSuccessWasCalled) {
        $onSuccessWasCalled = true;
    });

    $httpLoader->load('https://www.otsch.codes');
    expect($onSuccessWasCalled)->toBeTrue();

    $onSuccessWasCalled = false;
    $httpLoader->loadOrFail('https://www.otsch.codes');
    expect($onSuccessWasCalled)->toBeTrue();
})->with([200, 201, 202]);

test('It calls the onError hook on a failed request', function ($responseStatusCode) {
    $httpClient = Mockery::mock(ClientInterface::class);
    $httpClient->shouldReceive('sendRequest')->once()->andReturn(new Response($responseStatusCode));
    $httpLoader = new HttpLoader(new BotUserAgent('Foo'), $httpClient);
    $onErrorWasCalled = false;
    $httpLoader->onError(function () use (& $onErrorWasCalled) {
        $onErrorWasCalled = true;
    });

    $httpLoader->load('https://www.otsch.codes');
    expect($onErrorWasCalled)->toBeTrue();
})->with([400, 404, 422, 500]);

test('It throws an Exception when request fails in loadOrFail method', function () {
    $httpClient = Mockery::mock(ClientInterface::class);
    $httpClient->shouldReceive('sendRequest')->once()->andReturn(new Response(400));
    $httpLoader = new HttpLoader(new BotUserAgent('Foo'), $httpClient);
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

test('It automatically handles redirects', function (string $loadingMethod) {
    $httpClient = Mockery::mock(ClientInterface::class);
    $httpClient->shouldReceive('sendRequest')
        ->twice()
        ->andReturn(
            new Response(301, ['Location' => 'https://www.redirect.com']),
            new Response(200, [], 'YES')
        );
    $httpLoader = new HttpLoader(new BotUserAgent('Foo'), $httpClient);
    $requestResponseAggregate = $httpLoader->{$loadingMethod}('https://www.crwlr.software/packages');

    /** @var RespondedRequest $requestResponseAggregate */
    expect($requestResponseAggregate->requestedUri())->toBe('https://www.crwlr.software/packages');
    expect($requestResponseAggregate->effectiveUri())->toBe('https://www.redirect.com');
    expect($requestResponseAggregate->response->getBody()->getContents())->toBe('YES');
})->with(['load', 'loadOrFail']);

test('It calls request start and end tracking methods', function (string $loadingMethod) {
    $httpClient = Mockery::mock(ClientInterface::class);
    $httpClient->shouldReceive('sendRequest')->once()->andReturn(new Response(200));
    $httpLoader = new class (new BotUserAgent('Foo'), $httpClient) extends HttpLoader {
        public function trackRequestStart(?float $microtime = null): void
        {
            $this->logger()->info('track request start');
        }

        public function trackRequestEnd(?float $microtime = null): void
        {
            $this->logger()->info('track request end');
        }
    };
    $httpLoader->load('https://www.twitter.com');

    $output = $this->getActualOutput();
    expect($output)->toContain('track request start');
    expect($output)->toContain('track request end');
})->with(['load', 'loadOrFail']);

test('It automatically logs loading success message', function ($loadingMethod) {
    $httpClient = Mockery::mock(ClientInterface::class);
    $httpClient->shouldReceive('sendRequest')->once()->andReturn(new Response());
    $httpLoader = new HttpLoader(new BotUserAgent('Foo'), $httpClient);
    $httpLoader->{$loadingMethod}(new Request('GET', 'https://phpstan.org/'));

    $output = $this->getActualOutput();
    expect($output)->toContain('Loaded https://phpstan.org/');
})->with(['load', 'loadOrFail']);

test('It automatically logs loading error message in normal load method', function () {
    $httpClient = Mockery::mock(ClientInterface::class);
    $httpClient->shouldReceive('sendRequest')->once()->andReturn(new Response(500));
    $httpLoader = new HttpLoader(new BotUserAgent('Foo'), $httpClient);
    $httpLoader->load(new Request('GET', 'https://phpstan.org/'));

    $output = $this->getActualOutput();
    expect($output)->toContain('Failed to load https://phpstan.org/');
});

test('It automatically adds the User-Agent header before sending', function () {
    $httpClient = Mockery::mock(ClientInterface::class);
    $httpClient->shouldReceive('sendRequest')
        ->once()
        ->withArgs(function ($request) {
            return str_contains($request->getHeaderLine('User-Agent'), 'FooBot');
        })
        ->andReturn(new Response());
    $httpLoader = new HttpLoader(new BotUserAgent('FooBot'), $httpClient);
    $httpLoader->load('https://www.facebook.com');
});

test('It tries to get responses from cache', function () {
    $httpClient = Mockery::mock(ClientInterface::class);
    $httpClient->shouldNotReceive('sendRequest');
    $cache = Mockery::mock(CacheInterface::class);
    $cache->shouldReceive('has')->once()->andReturn(true);
    $cache->shouldReceive('get')
        ->once()
        ->andReturn(HttpResponseCacheItem::fromAggregate(
            new RespondedRequest(new Request('GET', '/'), new Response())
        ));
    $httpLoader = new HttpLoader(new BotUserAgent('FooBot'), $httpClient);
    $httpLoader->setCache($cache);
    $httpLoader->load('https://www.facebook.com');
});

test('It fails when it gets a failed response from cache', function () {
    $httpClient = Mockery::mock(ClientInterface::class);
    $cache = Mockery::mock(CacheInterface::class);
    $cache->shouldReceive('has')->once()->andReturn(true);
    $cache->shouldReceive('get')
        ->once()
        ->andReturn(HttpResponseCacheItem::fromAggregate(
            new RespondedRequest(new Request('GET', '/'), new Response(404))
        ));
    $httpLoader = new HttpLoader(new BotUserAgent('FooBot'), $httpClient);
    $httpLoader->setCache($cache);

    $onErrorWasCalled = false;
    $httpLoader->onError(function () use (& $onErrorWasCalled) {
        $onErrorWasCalled = true;
    });

    $httpLoader->load('https://www.facebook.com');
    expect($onErrorWasCalled)->toBeTrue();
});

test('It fails when it gets a failed response from cache in loadOrFail', function () {
    $httpClient = Mockery::mock(ClientInterface::class);
    $cache = Mockery::mock(CacheInterface::class);
    $cache->shouldReceive('has')->once()->andReturn(true);
    $cache->shouldReceive('get')
        ->once()
        ->andReturn(HttpResponseCacheItem::fromAggregate(
            new RespondedRequest(new Request('GET', 'facebook'), new Response(404))
        ));
    $httpLoader = new HttpLoader(new BotUserAgent('FooBot'), $httpClient);
    $httpLoader->setCache($cache);
    $httpLoader->loadOrFail('https://www.facebook.com');
})->throws(LoadingException::class);

test('It adds loaded responses to the cache when it has a cache', function ($loadingMethod) {
    $httpClient = Mockery::mock(ClientInterface::class);
    $httpClient->shouldReceive('sendRequest')->once()->andReturn(new Response());
    $cache = Mockery::mock(CacheInterface::class);
    $cache->shouldReceive('has')->once()->andReturn(false);
    $cache->shouldReceive('set')->once();
    $httpLoader = new HttpLoader(new BotUserAgent('FooBot'), $httpClient);
    $httpLoader->setCache($cache);
    $httpLoader->{$loadingMethod}('https://laravel.com/');
})->with(['load', 'loadOrFail']);

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

    $httpLoader = new HttpLoader(new BotUserAgent('Foo'), $httpClient);
    $httpLoader->load('https://www.crwlr.software/');
    $httpLoader->load('https://www.crwlr.software/blog');
    $httpLoader->loadOrFail('https://www.crwlr.software/contact');
    $httpLoader->loadOrFail('https://www.crwlr.software/packages');
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

    $httpLoader = new HttpLoader(new BotUserAgent('Foo'), $httpClient);
    $httpLoader->dontUseCookies();
    $httpLoader->load('https://www.crwlr.software/');
    $httpLoader->load('https://www.crwlr.software/blog');
    $httpLoader->loadOrFail('https://www.crwlr.software/contact');
    $httpLoader->loadOrFail('https://www.crwlr.software/packages');
});
