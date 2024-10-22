<?php

namespace tests\Loader\Http\Cookies;

use Crwlr\Crawler\Loader\Http\Cookies\CookieJar;
use Crwlr\Url\Url;
use GuzzleHttp\Psr7\Response;
use HeadlessChromium\Cookies\Cookie;
use HeadlessChromium\Cookies\CookiesCollection;

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

test('addFrom() works with a CookieCollection from the chrome-php lib', function () {
    $jar = new CookieJar();

    $jar->addFrom(Url::parse('https://www.crwl.io'), new CookiesCollection([
        new Cookie([
            'name' => 'foo',
            'value' => 'one',
            'domain' => '.www.crwl.io',
            'expires' => '1745068860',
            'max-age' => '86400',
            'secure' => true,
            'httpOnly' => true,
            'sameSite' => 'Strict',
        ]),
        new Cookie([
            'name' => 'bar',
            'value' => 'two',
            'domain' => '.www.crwl.io',
            'expires' => '1729603260.5272',
            'path' => '/bar',
        ]),
        new Cookie([
            'name' => 'baz',
            'value' => 'three',
            'domain' => '.www.crwl.io',
            'expires' => '1764076860.878',
        ]),
    ]));

    $allCookiesForDomain = $jar->allByDomain('crwl.io');

    expect($allCookiesForDomain)->toHaveCount(3)
        ->and($allCookiesForDomain['foo']->expires()?->dateTime()->format('Y-m-d H:i'))->toBe('2025-04-19 13:21')
        ->and($allCookiesForDomain['foo']->name())->toBe('foo')
        ->and($allCookiesForDomain['foo']->value())->toBe('one')
        ->and($allCookiesForDomain['foo']->domain())->toBe('www.crwl.io')
        ->and($allCookiesForDomain['foo']->maxAge())->toBe(86400)
        ->and($allCookiesForDomain['foo']->path())->toBeNull()
        ->and($allCookiesForDomain['foo']->secure())->toBeTrue()
        ->and($allCookiesForDomain['foo']->httpOnly())->toBeTrue()
        ->and($allCookiesForDomain['foo']->sameSite())->toBe('Strict')
        ->and($allCookiesForDomain['bar']->expires()?->dateTime()->format('Y-m-d H:i'))->toBe('2024-10-22 13:21')
        ->and($allCookiesForDomain['bar']->name())->toBe('bar')
        ->and($allCookiesForDomain['bar']->value())->toBe('two')
        ->and($allCookiesForDomain['bar']->domain())->toBe('www.crwl.io')
        ->and($allCookiesForDomain['bar']->maxAge())->toBeNull()
        ->and($allCookiesForDomain['bar']->path())->toBe('/bar')
        ->and($allCookiesForDomain['bar']->secure())->toBeFalse()
        ->and($allCookiesForDomain['bar']->httpOnly())->toBeFalse()
        ->and($allCookiesForDomain['bar']->sameSite())->toBe('Lax')
        ->and($allCookiesForDomain['baz']->expires()?->dateTime()->format('Y-m-d H:i'))->toBe('2025-11-25 13:21');
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
