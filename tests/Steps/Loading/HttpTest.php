<?php

namespace tests\Steps\Loading;

use Crwlr\Crawler\Input;
use Crwlr\Crawler\Loader\Http\HttpLoader;
use Crwlr\Crawler\Loader\Http\Messages\RespondedRequest;
use Crwlr\Crawler\Steps\Loading\Http;
use Crwlr\Url\Url;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use InvalidArgumentException;
use Mockery;
use Psr\Http\Message\RequestInterface;
use stdClass;

use function tests\helper_traverseIterable;

test('It can be invoked with a string as input', function () {
    $loader = Mockery::mock(HttpLoader::class);

    $loader->shouldReceive('load')->once();

    $step = (new Http('GET'))->addLoader($loader);

    helper_traverseIterable($step->invokeStep(new Input('https://www.foo.bar/baz')));
});

test('It can be invoked with a PSR-7 Uri object as input', function () {
    $loader = Mockery::mock(HttpLoader::class);

    $loader->shouldReceive('load')->once();

    $step = (new Http('GET'))->addLoader($loader);

    helper_traverseIterable($step->invokeStep(new Input(Url::parsePsr7('https://www.linkedin.com/'))));
});

test('It throws an InvalidArgumentExpection when invoked with something else as input', function () {
    $loader = Mockery::mock(HttpLoader::class);

    $step = (new Http('GET'))->addLoader($loader);

    helper_traverseIterable($step->invokeStep(new Input(new stdClass())));
})->throws(InvalidArgumentException::class);

test('You can set the request method via constructor', function (string $httpMethod) {
    $loader = Mockery::mock(HttpLoader::class);

    $loader->shouldReceive('load')->withArgs(function (RequestInterface $request) use ($httpMethod) {
        return $request->getMethod() === $httpMethod;
    })->once();

    $step = (new Http($httpMethod))->addLoader($loader);

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
            'application/signed-exchange;v=b3;q=0.9'
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

    $step = (new Http('GET', $headers))->addLoader($loader);

    helper_traverseIterable($step->invokeStep(new Input('https://www.crwlr.software/packages/url')));
});

test('You can set request body via constructor', function () {
    $loader = Mockery::mock(HttpLoader::class);

    $body = 'This is the request body';

    $loader->shouldReceive('load')->withArgs(function (RequestInterface $request) use ($body) {
        return $request->getBody()->getContents() === $body;
    })->once();

    $step = (new Http('PATCH', [], $body))->addLoader($loader);

    helper_traverseIterable($step->invokeStep(new Input('https://github.com/')));
});

test('You can set the http version for the request via constructor', function (string $httpVersion) {
    $loader = Mockery::mock(HttpLoader::class);

    $loader->shouldReceive('load')->withArgs(function (RequestInterface $request) use ($httpVersion) {
        return $request->getProtocolVersion() === $httpVersion;
    })->once();

    $step = (new Http('PATCH', [], 'body', $httpVersion))->addLoader($loader);

    helper_traverseIterable($step->invokeStep(new Input('https://packagist.org/packages/crwlr/url')));
})->with(['1.0', '1.1', '2.0']);

test('It has static methods to create instances with all the different http methods', function (string $httpMethod) {
    $loader = Mockery::mock(HttpLoader::class);

    $loader->shouldReceive('load')->withArgs(function (RequestInterface $request) use ($httpMethod) {
        return $request->getMethod() === $httpMethod;
    })->once();

    $step = (Http::{strtolower($httpMethod)}())->addLoader($loader);

    helper_traverseIterable($step->invokeStep(new Input('https://dev.to/otsch')));
})->with(['GET', 'POST', 'PUT', 'PATCH', 'DELETE']);

it(
    'calls the loadOrFail() loader method when the stopOnErrorResponse() method was called',
    function (string $httpMethod) {
        $loader = Mockery::mock(HttpLoader::class);

        $loader->shouldReceive('loadOrFail')->withArgs(function (RequestInterface $request) use ($httpMethod) {
            return $request->getMethod() === $httpMethod;
        })->once()->andReturn(new RespondedRequest(new Request('GET', '/foo'), new Response(200)));

        $step = (Http::{strtolower($httpMethod)}())
            ->addLoader($loader)
            ->stopOnErrorResponse();

        helper_traverseIterable($step->invokeStep(new Input('https://example.com/otsch')));
    }
)->with(['GET', 'POST', 'PUT', 'PATCH', 'DELETE']);
