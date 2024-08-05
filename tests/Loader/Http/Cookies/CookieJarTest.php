<?php

namespace tests\Loader\Http\Cookies;

use Crwlr\Crawler\Loader\Http\Cookies\CookieJar;
use Crwlr\Url\Url;
use GuzzleHttp\Psr7\Response;

test('addFrom works with a string url', function () {
    $jar = new CookieJar();

    $jar->addFrom('https://www.crwl.io', new Response(200, [
        'Set-Cookie' => ['cook13=v4lu3; Secure'],
    ]));

    $allCookiesForDomain = $jar->allByDomain('crwl.io');

    expect($allCookiesForDomain)->toHaveCount(1);
});

test('addFrom works with an instance of UriInterface', function () {
    $jar = new CookieJar();

    $jar->addFrom(Url::parsePsr7('https://www.crwl.io'), new Response(200, [
        'Set-Cookie' => ['cook13=v4lu3; Secure'],
    ]));

    $allCookiesForDomain = $jar->allByDomain('crwl.io');

    expect($allCookiesForDomain)->toHaveCount(1);
});

test('addFrom works with an instance of Url', function () {
    $jar = new CookieJar();

    $jar->addFrom(Url::parse('https://www.crwl.io'), new Response(200, [
        'Set-Cookie' => ['cook13=v4lu3; Secure'],
    ]));

    $allCookiesForDomain = $jar->allByDomain('crwl.io');

    expect($allCookiesForDomain)->toHaveCount(1);
});

it('adds all cookies from a response', function () {
    $jar = new CookieJar();

    $jar->addFrom(Url::parse('https://www.otsch.codes'), new Response(200, [
        'Set-Cookie' => ['cook13=v4lu3; Secure', 'anotherCookie=andItsValue', 'oneMoreCookie=dough'],
    ]));

    $allCookiesForDomain = $jar->allByDomain('otsch.codes');

    expect($allCookiesForDomain)->toHaveCount(3);
});

it('returns all cookies that should be sent to a url', function () {
    $jar = new CookieJar();

    $jar->addFrom(Url::parse('https://www.otsch.codes/blog'), new Response(200, [
        'Set-Cookie' => [
            'cook13=v4lu3; Secure',
            '__Host-anotherCookie=andItsValue; Secure; Path=/',
            'oneMoreCookie=dough',
        ],
    ]));

    expect($jar->getFor('https://www.otsch.codes/contact'))->toHaveCount(3)
        ->and($jar->getFor('https://jobs.otsch.codes/index'))->toHaveCount(2)
        ->and($jar->getFor('http://games.otsch.codes'))->toHaveCount(1);
});
