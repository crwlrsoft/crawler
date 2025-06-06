<?php

namespace tests\Steps\Loading;

use Closure;
use Crwlr\Crawler\Input;
use Crwlr\Crawler\Loader\Http\Exceptions\LoadingException;
use Crwlr\Crawler\Loader\Http\HeadlessBrowserLoaderHelper;
use Crwlr\Crawler\Loader\Http\HttpLoader;
use Crwlr\Crawler\Loader\Http\Messages\RespondedRequest;
use Crwlr\Crawler\Steps\Loading\Http;
use Crwlr\Crawler\Steps\Loading\Http\Browser\BrowserAction;
use Crwlr\Url\Url;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Utils;
use InvalidArgumentException;
use Mockery;
use Psr\Http\Message\RequestInterface;
use stdClass;
use tests\_Stubs\DummyLogger;
use Throwable;

use function tests\helper_getRespondedRequest;
use function tests\helper_invokeStepWithInput;
use function tests\helper_nonBotUserAgent;
use function tests\helper_traverseIterable;

it('can be invoked with a string as input', function () {
    $loader = Mockery::mock(HttpLoader::class);

    $loader->shouldReceive('load')->once();

    $step = (new Http('GET'))->setLoader($loader);

    helper_traverseIterable($step->invokeStep(new Input('https://www.foo.bar/baz')));
});

it('can be invoked with a PSR-7 Uri object as input', function () {
    $loader = Mockery::mock(HttpLoader::class);

    $loader->shouldReceive('load')->once();

    $step = (new Http('GET'))->setLoader($loader);

    helper_traverseIterable($step->invokeStep(new Input(Url::parsePsr7('https://www.linkedin.com/'))));
});

it('logs an error message when invoked with something else as input', function () {
    $logger = new DummyLogger();

    $loader = Mockery::mock(HttpLoader::class);

    $step = (new Http('GET'))->setLoader($loader)->addLogger($logger);

    helper_traverseIterable($step->invokeStep(new Input(new stdClass())));

    expect($logger->messages)->not->toBeEmpty()
        ->and($logger->messages[0]['message'])->toStartWith(
            'The Crwlr\Crawler\Steps\Loading\Http step was called with input that it can not work with:',
        )
        ->and($logger->messages[0]['message'])->toEndWith('. The invalid input is of type object.');
});

it('logs an error message when invoked with a relative reference URI', function () {
    $logger = new DummyLogger();

    $loader = new HttpLoader(helper_nonBotUserAgent(), logger: $logger);

    $step = (new Http('GET'))->setLoader($loader)->addLogger($logger);

    helper_invokeStepWithInput($step, '/foo/bar');

    expect($logger->messages)->not->toBeEmpty()
        ->and($logger->messages[0]['message'])->toBe(
            'Invalid input URL: /foo/bar - The URI is a relative reference and therefore can\'t be loaded.',
        );
});

it('catches the exception and logs an error when feeded with an invalid URL', function () {
    $loader = Mockery::mock(HttpLoader::class);

    $logger = new DummyLogger();

    $step = (new Http('GET'))->setLoader($loader);

    $step->addLogger($logger);

    helper_traverseIterable($step->invokeStep(new Input('https://')));

    expect($logger->messages)->toHaveCount(1)
        ->and($logger->messages[0]['level'])->toBe('error')
        ->and($logger->messages[0]['message'])->toBe(
            'The Crwlr\\Crawler\\Steps\\Loading\\Http step was called with input that it can not work with: https:// ' .
            'is not a valid URL.',
        );
});

it('throws an exception when invoked with a relative reference URI and stopOnErrorResponse() was called', function () {
    $logger = new DummyLogger();

    $loader = new HttpLoader(helper_nonBotUserAgent(), logger: $logger);

    $step = (new Http('GET'))->setLoader($loader)->addLogger($logger);

    $step->stopOnErrorResponse();

    helper_invokeStepWithInput($step, '/foo/bar');
})->throws(InvalidArgumentException::class);

test('You can set the request method via constructor', function (string $httpMethod) {
    $loader = Mockery::mock(HttpLoader::class);

    $loader->shouldReceive('load')->withArgs(function (RequestInterface $request) use ($httpMethod) {
        return $request->getMethod() === $httpMethod;
    })->once();

    if ($httpMethod !== 'GET') {
        $loader->shouldReceive('usesHeadlessBrowser')->andReturnFalse();
    }

    $step = (new Http($httpMethod))->setLoader($loader);

    helper_traverseIterable($step->invokeStep(new Input('https://www.foo.bar/baz')));
})->with(['GET', 'POST', 'PUT', 'PATCH', 'DELETE']);

test('You can set request headers via constructor', function () {
    $loader = Mockery::mock(HttpLoader::class);

    $headers = [
        'Accept' => [
            'text/html',
            'application/xhtml+xml',
            'application/xml;q=0.9',
            'image/avif',
            'image/webp',
            'image/apng',
            '*/*;q=0.8',
            'application/signed-exchange;v=b3;q=0.9',
        ],
        'Accept-Encoding' => ['gzip', 'deflate', 'br'],
        'Accept-Language' => ['de-DE', 'de;q=0.9', 'en-US;q=0.8', 'en;q=0.7'],
    ];

    $loader->shouldReceive('load')->withArgs(function (RequestInterface $request) use ($headers) {
        foreach ($headers as $headerName => $values) {
            if (!$request->getHeader($headerName) || $request->getHeader($headerName) !== $values) {
                return false;
            }
        }

        return true;
    })->once();

    $step = (new Http('GET', $headers))->setLoader($loader);

    helper_traverseIterable($step->invokeStep(new Input('https://www.crwlr.software/packages/url')));
});

test('You can set request body via constructor', function () {
    $loader = Mockery::mock(HttpLoader::class);

    $body = 'This is the request body';

    $loader->shouldReceive('load')->withArgs(function (RequestInterface $request) use ($body) {
        return $request->getBody()->getContents() === $body;
    })->once();

    $loader->shouldReceive('usesHeadlessBrowser')->andReturnFalse();

    $step = (new Http('PATCH', [], $body))->setLoader($loader);

    helper_traverseIterable($step->invokeStep(new Input('https://github.com/')));
});

test('You can set the http version for the request via constructor', function (string $httpVersion) {
    $loader = Mockery::mock(HttpLoader::class);

    $loader->shouldReceive('load')->withArgs(function (RequestInterface $request) use ($httpVersion) {
        return $request->getProtocolVersion() === $httpVersion;
    })->once();

    $loader->shouldReceive('usesHeadlessBrowser')->andReturnFalse();

    $step = (new Http('PATCH', [], 'body', $httpVersion))->setLoader($loader);

    helper_traverseIterable($step->invokeStep(new Input('https://packagist.org/packages/crwlr/url')));
})->with(['1.0', '1.1', '2.0']);

it('has static methods to create instances with all the different http methods', function (string $httpMethod) {
    $loader = Mockery::mock(HttpLoader::class);

    $loader->shouldReceive('load')->withArgs(function (RequestInterface $request) use ($httpMethod) {
        return $request->getMethod() === $httpMethod;
    })->once();

    if ($httpMethod !== 'GET') {
        $loader->shouldReceive('usesHeadlessBrowser')->andReturnFalse();
    }

    $step = (Http::{strtolower($httpMethod)}())->setLoader($loader);

    helper_traverseIterable($step->invokeStep(new Input('https://dev.to/otsch')));
})->with(['GET', 'POST', 'PUT', 'PATCH', 'DELETE']);

it(
    'calls the loadOrFail() loader method when the stopOnErrorResponse() method was called',
    function (string $httpMethod) {
        $loader = Mockery::mock(HttpLoader::class);

        $loader->shouldReceive('loadOrFail')->withArgs(function (RequestInterface $request) use ($httpMethod) {
            return $request->getMethod() === $httpMethod;
        })->once()->andReturn(new RespondedRequest(new Request('GET', '/foo'), new Response(200)));

        if ($httpMethod !== 'GET') {
            $loader->shouldReceive('usesHeadlessBrowser')->andReturnFalse();
        }

        $step = (Http::{strtolower($httpMethod)}())
            ->setLoader($loader)
            ->stopOnErrorResponse();

        helper_traverseIterable($step->invokeStep(new Input('https://example.com/otsch')));
    },
)->with(['GET', 'POST', 'PUT', 'PATCH', 'DELETE']);

test('you can keep response properties with their aliases', function () {
    $loader = Mockery::mock(HttpLoader::class);

    $loader->shouldReceive('load')->once()->andReturn(
        new RespondedRequest(
            new Request('GET', 'https://www.example.com/testresponse'),
            new Response(202, ['foo' => 'bar'], Utils::streamFor('testbody')),
        ),
    );

    $step = Http::get()
        ->setLoader($loader)
        ->keep(['url', 'status', 'headers', 'body']);

    $outputs = helper_invokeStepWithInput($step);

    expect($outputs)->toHaveCount(1)
        ->and($outputs[0]->keep)->toBe([
            'url' => 'https://www.example.com/testresponse',
            'status' => 202,
            'headers' => ['foo' => ['bar']],
            'body' => 'testbody',
        ]);

});

test(
    'the value behind url and uri is the effectiveUri',
    function (string $outputKey) {
        $loader = Mockery::mock(HttpLoader::class);

        $respondedRequest = new RespondedRequest(
            new Request('GET', 'https://www.example.com/testresponse'),
            new Response(202, ['foo' => 'bar'], Utils::streamFor('testbody')),
        );

        $respondedRequest->addRedirectUri('https://www.example.com/testresponseredirect');

        $loader->shouldReceive('load')->once()->andReturn($respondedRequest);

        $step = Http::get()
            ->setLoader($loader)
            ->keep([$outputKey]);

        $outputs = helper_invokeStepWithInput($step);

        expect($outputs)->toHaveCount(1)
            ->and($outputs[0]->keep)->toBe([$outputKey => 'https://www.example.com/testresponseredirect']);
    },
)->with(['url', 'uri']);

it('gets the URL for the request from an input array when useInputKeyAsUrl() was called', function () {
    $inputArray = [
        'foo' => 'bar',
        'someUrl' => 'https://www.example.com/baz',
    ];

    $loader = Mockery::mock(HttpLoader::class);

    $loader->shouldReceive('load')->withArgs(function (RequestInterface $request) use ($inputArray) {
        return $request->getUri()->__toString() === $inputArray['someUrl'];
    })->once()->andReturn(new RespondedRequest(new Request('GET', 'https://www.example.com/baz'), new Response(200)));

    $step = Http::get()
        ->setLoader($loader)
        ->useInputKeyAsUrl('someUrl');

    helper_invokeStepWithInput($step, $inputArray);
});

it(
    'automatically gets the URL for the request from an input array when it contains an url or uri key',
    function ($key) {
        $inputArray = [
            'foo' => 'bar',
            $key => 'https://www.example.com/baz',
        ];

        $loader = Mockery::mock(HttpLoader::class);

        $loader->shouldReceive('load')->withArgs(function (RequestInterface $request) use ($inputArray, $key) {
            return $request->getUri()->__toString() === $inputArray[$key];
        })->once()->andReturn(new RespondedRequest(new Request('GET', 'https://www.example.com/baz'), new Response(200)));

        $step = Http::get()
            ->setLoader($loader);

        helper_invokeStepWithInput($step, $inputArray);
    },
)->with(['url', 'uri']);

it('gets the body for the request from an input array when useInputKeyAsBody() was called', function () {
    $inputArray = [
        'foo' => 'bar',
        'someUrl' => 'https://www.example.com/baz',
        'someBodyThatIUsedToKnow' => 'foo=bar&baz=quz',
    ];

    $loader = Mockery::mock(HttpLoader::class);

    $loader
        ->shouldReceive('load')
        ->withArgs(function (RequestInterface $request) use ($inputArray) {
            return $request->getBody()->getContents() === $inputArray['someBodyThatIUsedToKnow'];
        })
        ->once()
        ->andReturn(new RespondedRequest(new Request('GET', 'https://www.example.com/baz'), new Response(200)));

    $step = Http::get()
        ->setLoader($loader)
        ->useInputKeyAsUrl('someUrl')
        ->useInputKeyAsBody('someBodyThatIUsedToKnow');

    helper_invokeStepWithInput($step, $inputArray);
});

it('gets as single header for the request from an input array when useInputKeyAsHeader() was called', function () {
    $inputArray = [
        'foo' => 'bar',
        'someUrl' => 'https://www.example.com/baz',
        'someHeader' => 'someHeaderValue',
    ];

    $loader = Mockery::mock(HttpLoader::class);

    $loader
        ->shouldReceive('load')
        ->withArgs(function (RequestInterface $request) use ($inputArray) {
            return $request->getHeader('header-name-x') === [$inputArray['someHeader']];
        })
        ->once()
        ->andReturn(new RespondedRequest(new Request('GET', 'https://www.example.com/baz'), new Response(200)));

    $step = Http::get()
        ->setLoader($loader)
        ->useInputKeyAsUrl('someUrl')
        ->useInputKeyAsHeader('someHeader', 'header-name-x');

    helper_invokeStepWithInput($step, $inputArray);
});

it('uses the input key as header name if no header name defined as argument', function () {
    $inputArray = [
        'foo' => 'bar',
        'url' => 'https://www.example.com/baz',
        'header-name' => 'someHeaderValue',
    ];

    $loader = Mockery::mock(HttpLoader::class);

    $loader
        ->shouldReceive('load')
        ->withArgs(function (RequestInterface $request) use ($inputArray) {
            return $request->getHeader('header-name') === [$inputArray['header-name']];
        })
        ->once()
        ->andReturn(new RespondedRequest(new Request('GET', 'https://www.example.com/baz'), new Response(200)));

    $step = Http::get()
        ->setLoader($loader)
        ->useInputKeyAsHeader('header-name');

    helper_invokeStepWithInput($step, $inputArray);
});

it('merges header values if you provide a static header value and use an input value as header', function () {
    $inputArray = [
        'foo' => 'bar',
        'someUrl' => 'https://www.example.com/baz',
        'someHeader' => 'someHeaderValue',
    ];

    $loader = Mockery::mock(HttpLoader::class);

    $loader
        ->shouldReceive('load')
        ->withArgs(function (RequestInterface $request) use ($inputArray) {
            return $request->getHeader('header-name-x') === ['foo', $inputArray['someHeader']];
        })
        ->once()
        ->andReturn(new RespondedRequest(new Request('GET', 'https://www.example.com/baz'), new Response(200)));

    $step = Http::get(['header-name-x' => 'foo'])
        ->setLoader($loader)
        ->useInputKeyAsUrl('someUrl')
        ->useInputKeyAsHeader('someHeader', 'header-name-x');

    helper_invokeStepWithInput($step, $inputArray);
});

test('you can use useInputKeyAsHeader() multiple times', function () {
    $inputArray = [
        'foo' => 'bar',
        'someUrl' => 'https://www.example.com/baz',
        'someHeader' => 'someHeaderValue',
        'anotherHeader' => 'anotherHeaderValue',
    ];

    $loader = Mockery::mock(HttpLoader::class);

    $loader
        ->shouldReceive('load')
        ->withArgs(function (RequestInterface $request) use ($inputArray) {
            return $request->getHeader('header-name-x') === [$inputArray['someHeader']] &&
                $request->getHeader('header-name-y') === [$inputArray['anotherHeader']];
        })
        ->once()
        ->andReturn(new RespondedRequest(new Request('GET', 'https://www.example.com/baz'), new Response(200)));

    $step = Http::get()
        ->setLoader($loader)
        ->useInputKeyAsUrl('someUrl')
        ->useInputKeyAsHeader('someHeader', 'header-name-x')
        ->useInputKeyAsHeader('anotherHeader', 'header-name-y');

    helper_invokeStepWithInput($step, $inputArray);
});

it('gets multiple headers from an input array using useInputKeyAsHeaders()', function () {
    $inputArray = [
        'foo' => 'bar',
        'someUrl' => 'https://www.example.com/baz',
        'customHeaders' => [
            'header-name-x' => 'foo',
            'header-name-y' => ['bar', 'baz'],
        ],
    ];

    $loader = Mockery::mock(HttpLoader::class);

    $loader
        ->shouldReceive('load')
        ->withArgs(function (RequestInterface $request) use ($inputArray) {
            $customHeaders = $inputArray['customHeaders'];

            $yHeaderExpectedValue = array_merge(['quz'], $customHeaders['header-name-y']);

            return $request->getHeader('header-name-x') === [$customHeaders['header-name-x']] &&
                $request->getHeader('header-name-y') === $yHeaderExpectedValue;
        })
        ->once()
        ->andReturn(new RespondedRequest(new Request('GET', 'https://www.example.com/baz'), new Response(200)));

    $step = Http::get(['header-name-y' => 'quz'])
        ->setLoader($loader)
        ->useInputKeyAsUrl('someUrl')
        ->useInputKeyAsHeaders('customHeaders');

    helper_invokeStepWithInput($step, $inputArray);
});

it('uses a static URL when defined', function () {
    $input = 'foo';

    $loader = Mockery::mock(HttpLoader::class);

    $loader
        ->shouldReceive('load')
        ->withArgs(function (RequestInterface $request) {
            return $request->getUri()->__toString() === 'https://www.example.com/servus';
        })
        ->once()
        ->andReturn(new RespondedRequest(new Request('GET', 'https://www.example.com/servus'), new Response(200)));

    $step = Http::get()
        ->setLoader($loader)
        ->staticUrl('https://www.example.com/servus');

    helper_invokeStepWithInput($step, $input);
});

it('resolves variables in a static URL from input data', function () {
    $input = ['one' => 'foo', 'two' => 'bar'];

    $loader = Mockery::mock(HttpLoader::class);

    $loader->shouldReceive('usesHeadlessBrowser')->andReturn(false);

    $loader
        ->shouldReceive('load')
        ->withArgs(function (RequestInterface $request) {
            return $request->getUri()->__toString() === 'https://www.example.com/foo/bar/baz';
        })
        ->once()
        ->andReturn(new RespondedRequest(new Request('GET', 'https://www.example.com/foo/bar/baz'), new Response(200)));

    $step = Http::get()
        ->setLoader($loader)
        ->staticUrl('https://www.example.com/[crwl:\'one\']/[crwl:two]/baz');

    helper_invokeStepWithInput($step, $input);
});

it('resolves variables in the request body from input data', function () {
    $input = [
        'url' => 'https://www.example.com/foo',
        'hey' => 'ho',
        'yo' => 'lo',
    ];

    $loader = Mockery::mock(HttpLoader::class);

    $loader->shouldReceive('usesHeadlessBrowser')->andReturn(false);

    $loader
        ->shouldReceive('load')
        ->withArgs(function (RequestInterface $request) {
            $bodyString = Http::getBodyString($request);

            return $bodyString === 'Ho ho ho and lo asdf';
        })
        ->once()
        ->andReturn(new RespondedRequest(new Request('GET', 'https://www.example.com/foo'), new Response(200)));

    $step = Http::post(body: 'Ho ho [crwl:hey] and [crwl:yo] asdf')
        ->setLoader($loader);

    helper_invokeStepWithInput($step, $input);
});

it('resolves variables in request headers from input data', function () {
    $input = [
        'url' => 'https://www.example.com/foo',
        'encoding' => 'deflate, br',
        'language' => 'de-DE,de;q=0.9,en-US;q=0.8,en;q=0.7',
    ];

    $loader = Mockery::mock(HttpLoader::class);

    $loader
        ->shouldReceive('load')
        ->withArgs(function (RequestInterface $request) {
            return $request->getHeaderLine('Accept-Encoding') === 'gzip, deflate, br, zstd' &&
                $request->getHeaderLine('Accept-Language') === 'de-DE,de;q=0.9,en-US;q=0.8,en;q=0.7';
        })
        ->once()
        ->andReturn(new RespondedRequest(new Request('GET', 'https://www.example.com/foo'), new Response(200)));

    $step = Http::get([
        'Accept-Encoding' => 'gzip, [crwl:"encoding"], zstd',
        'Accept-Language' => '[crwl:language]',
    ])
        ->setLoader($loader);

    helper_invokeStepWithInput($step, $input);
});

test(
    'the getBodyString() method does not generate a warning, when the response contains a ' .
    'Content-Type: application/x-gzip header, but the content actually isn\'t compressed',
    function () {
        $warnings = [];

        set_error_handler(function ($errno, $errstr) use (&$warnings) {
            if ($errno === E_WARNING) {
                $warnings[] = $errstr;
            }

            return false;
        });

        $response = helper_getRespondedRequest(
            url: 'https://example.com/yolo',
            responseHeaders: ['Content-Type' => 'application/x-gzip'],
            responseBody: 'Servas!',
        );

        $string = Http::getBodyString($response);

        restore_error_handler();

        expect($warnings)->toBeEmpty()
            ->and($string)->toBe('Servas!');
    },
);

it('rejects post browser navigate hooks, when the HTTP method is not GET', function (string $httpMethod) {
    $logger = new DummyLogger();

    $step = (new Http($httpMethod))->addLogger($logger)->postBrowserNavigateHook(BrowserAction::wait(1.0));

    expect($logger->messages)->toHaveCount(1)
        ->and($logger->messages[0]['message'])->toBe(
            'A ' . $httpMethod . ' request cannot be executed using the (headless) browser, so post browser ' .
            'navigate hooks can\'t be defined for this step either.',
        )
        ->and(invade($step)->postBrowserNavigateHooks)->toBe([]);
})->with(['POST', 'PUT', 'PATCH', 'DELETE']);

it(
    'calls the HttpLoader::skipCacheForNextRequest() method before calling load when the skipCache() method was called',
    function () {
        $loader = Mockery::mock(HttpLoader::class);

        $respondedRequest = new RespondedRequest(
            new Request('GET', 'https://www.example.com/blog/posts'),
            new Response(200, body: Utils::streamFor('blog posts')),
        );

        $loader->shouldReceive('skipCacheForNextRequest')->once();

        $loader->shouldReceive('load')->once()->andReturn($respondedRequest);

        $step = Http::get()->setLoader($loader)->skipCache();

        helper_invokeStepWithInput($step);
    },
);

it(
    'calls the HttpLoader::skipCacheForNextRequest() method before calling loadOrFail() when the skipCache() method ' .
    'was called',
    function () {
        $loader = Mockery::mock(HttpLoader::class);

        $respondedRequest = new RespondedRequest(
            new Request('GET', 'https://www.example.com/blog/posts'),
            new Response(200, body: Utils::streamFor('blog posts')),
        );

        $loader->shouldReceive('skipCacheForNextRequest')->once();

        $loader->shouldReceive('loadOrFail')->once()->andReturn($respondedRequest);

        $step = Http::get()->setLoader($loader)->skipCache()->stopOnErrorResponse();

        helper_invokeStepWithInput($step);
    },
);

it(
    'switches the loader to use the browser, when useBrowser() was called and the loader is configured to use the ' .
    'HTTP client',
    function () {
        $loader = Mockery::mock(HttpLoader::class);

        $loader->shouldReceive('usesHeadlessBrowser')->once()->andReturn(false);

        $loader->shouldReceive('useHeadlessBrowser')->once();

        $loader->shouldReceive('useHttpClient')->once();

        $respondedRequest = new RespondedRequest(
            new Request('GET', 'https://www.example.com/hello/world'),
            new Response(200, body: Utils::streamFor('Hello World!')),
        );

        $loader->shouldReceive('load')->once()->andReturn($respondedRequest);

        $step = Http::get()->setLoader($loader)->useBrowser();

        helper_invokeStepWithInput($step);
    },
);

it(
    'switches the loader to use the browser, when stopOnErrorResponse() and useBrowser() was called and the loader ' .
    'is configured to use the HTTP client',
    function () {
        $loader = Mockery::mock(HttpLoader::class);

        $loader->shouldReceive('usesHeadlessBrowser')->once()->andReturn(false);

        $loader->shouldReceive('useHeadlessBrowser')->once();

        $loader->shouldReceive('useHttpClient')->once();

        $respondedRequest = new RespondedRequest(
            new Request('GET', 'https://www.example.com/hello/world'),
            new Response(200, body: Utils::streamFor('Hello World!')),
        );

        $loader->shouldReceive('loadOrFail')->once()->andReturn($respondedRequest);

        $step = Http::get()->setLoader($loader)->stopOnErrorResponse()->useBrowser();

        helper_invokeStepWithInput($step);
    },
);

it(
    'does not switch the loader to use the browser, when useBrowser() was called, the loader is configured to use ' .
    'the HTTP client, but the request method is not GET',
    function (string $httpMethod) {
        $logger = new DummyLogger();

        $loader = Mockery::mock(HttpLoader::class);

        $loader->shouldReceive('usesHeadlessBrowser')->once()->andReturn(false);

        $loader->shouldNotReceive('useHeadlessBrowser');

        $respondedRequest = new RespondedRequest(
            new Request($httpMethod, 'https://www.example.com/something'),
            new Response(200, body: Utils::streamFor('Something!')),
        );

        $loader->shouldReceive('load')->once()->andReturn($respondedRequest);

        $step = Http::{$httpMethod}()->setLoader($loader)->addLogger($logger)->useBrowser();

        helper_invokeStepWithInput($step);

        expect($logger->messages)->toHaveCount(1)
            ->and($logger->messages[0]['message'])->toBe(
                'The (headless) browser can only be used for GET requests! Therefore this step will use the HTTP ' .
                'client for loading.',
            );
    },
)->with(['post', 'put', 'patch', 'delete']);

it(
    'automatically switches the loader to use the HTTP client, when the HTTP method is not GET and the loader is ' .
    'configured to use the browser',
    function (string $httpMethod) {
        $logger = new DummyLogger();

        $loader = Mockery::mock(HttpLoader::class);

        $loader->shouldReceive('usesHeadlessBrowser')->once()->andReturn(true);

        $loader->shouldReceive('useHttpClient')->once();

        $loader->shouldReceive('useHeadlessBrowser')->once();

        $respondedRequest = new RespondedRequest(
            new Request($httpMethod, 'https://www.example.com/something'),
            new Response(200, body: Utils::streamFor('Something!')),
        );

        $loader->shouldReceive('load')->once()->andReturn($respondedRequest);

        $step = Http::{$httpMethod}()->setLoader($loader)->addLogger($logger)->useBrowser();

        helper_invokeStepWithInput($step);

        expect($logger->messages)->toHaveCount(1)
            ->and($logger->messages[0]['message'])->toBe(
                'The (headless) browser can only be used for GET requests! Therefore this step will use the HTTP ' .
                'client for loading.',
            );
    },
)->with(['post', 'put', 'patch', 'delete']);

it(
    'switches back the loader to use the HTTP client, when stopOnErrorResponse() and useBrowser() was called and ' .
    'loading throws an exception',
    function () {
        $loader = Mockery::mock(HttpLoader::class);

        $loader->shouldReceive('usesHeadlessBrowser')->once()->andReturn(false);

        $loader->shouldReceive('useHeadlessBrowser')->once();

        $loader->shouldReceive('useHttpClient')->once();

        $loader->shouldReceive('loadOrFail')->once()->andThrow(new LoadingException('error message'));

        $step = Http::get()->setLoader($loader)->stopOnErrorResponse()->useBrowser();

        try {
            helper_invokeStepWithInput($step);
        } catch (Throwable $exception) {
        }
    },
);

it(
    'does not call the useHeadlessBrowser() method of the loader, when useBrowser() was called and the loader is ' .
    'already configured to use the browser',
    function () {
        $loader = Mockery::mock(HttpLoader::class);

        $loader->shouldReceive('usesHeadlessBrowser')->once()->andReturn(true);

        $loader->shouldNotReceive('useHeadlessBrowser');

        $loader->shouldNotReceive('useHttpClient');

        $respondedRequest = new RespondedRequest(
            new Request('GET', 'https://www.example.com/hello/world'),
            new Response(200, body: Utils::streamFor('Hello World!')),
        );

        $loader->shouldReceive('load')->once()->andReturn($respondedRequest);

        $step = Http::get()->setLoader($loader)->useBrowser();

        helper_invokeStepWithInput($step);
    },
);

it(
    'does not call the useHeadlessBrowser() method of the loader, when stopOnErrorResponse() and useBrowser() was ' .
    'called and the loader is already configured to use the browser',
    function () {
        $loader = Mockery::mock(HttpLoader::class);

        $loader->shouldReceive('usesHeadlessBrowser')->once()->andReturn(true);

        $loader->shouldNotReceive('useHeadlessBrowser');

        $loader->shouldNotReceive('useHttpClient');

        $respondedRequest = new RespondedRequest(
            new Request('GET', 'https://www.example.com/hello/world'),
            new Response(200, body: Utils::streamFor('Hello World!')),
        );

        $loader->shouldReceive('loadOrFail')->once()->andReturn($respondedRequest);

        $step = Http::get()->setLoader($loader)->stopOnErrorResponse()->useBrowser();

        helper_invokeStepWithInput($step);
    },
);

it(
    'sets post browser navigate hooks, when useBrowser() was called and the loader is configured to use the HTTP ' .
    'client',
    function () {
        $loader = Mockery::mock(HttpLoader::class)->makePartial();

        $browserHelperMock = Mockery::mock(HeadlessBrowserLoaderHelper::class);

        $loader->shouldReceive('browser')->andReturn($browserHelperMock);

        $browserHelperMock
            ->shouldReceive('setTempPostNavigateHooks')
            ->once()
            ->withArgs(function (array $hooks) {
                return $hooks[0] instanceof Closure;
            });

        $respondedRequest = new RespondedRequest(
            new Request('GET', 'https://www.example.com/woop'),
            new Response(200, body: Utils::streamFor('Woop')),
        );

        $loader->shouldReceive('load')->once()->andReturn($respondedRequest);

        $step = Http::get()->setLoader($loader)->useBrowser()->postBrowserNavigateHook(BrowserAction::wait(1.0));

        helper_invokeStepWithInput($step);
    },
);
