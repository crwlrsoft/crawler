<?php

namespace tests\Http;

use Crwlr\Crawler\Exceptions\InvalidCookieException;
use Crwlr\Crawler\Http\Cookie;
use Crwlr\Crawler\Http\Date;
use Crwlr\Url\Url;
use Psr\Http\Message\UriInterface;

test('It can be created with received from url as string argrument', function () {
    $cookie = new Cookie('https://www.crwlr.software/packages', 'cookieName=cookieValue');
    expect($cookie)->toBeInstanceOf(Cookie::class);
});

test('It can be created with received from url as Url object', function () {
    $cookie = new Cookie(Url::parse('https://www.crwlr.software/packages'), 'cookieName=cookieValue');
    expect($cookie)->toBeInstanceOf(Cookie::class);
});

test('It provides the received from url as PSR-7 Uri object', function () {
    $cookie = new Cookie('https://www.crwlr.software/contact', 'cookieName=cookieValue');
    expect($cookie->receivedFromUrl())->toBeInstanceOf(UriInterface::class);
});

test('It must at least have a name and value', function () {
    new Cookie(Url::parse('https://www.crwlr.software/packages'), 'cookieNameWithoutValueIsInvalid');
})->throws(InvalidCookieException::class);

test('It parses the name and value of the cookie', function () {
    $cookie = new Cookie('https://www.crwlr.software/blog', 'crwlrsoftware_session=foobar');
    expect($cookie->name())->toBe('crwlrsoftware_session');
    expect($cookie->value())->toBe('foobar');
});

test('It automatically sets the domain based on the received from url when no attribute is included', function () {
    // https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/Set-Cookie
    // If omitted, this attribute defaults to the host of the current document URL, not including subdomains.
    $cookie = new Cookie('https://www.otsch.codes/blog', 'otschcodes_session=cook13');
    expect($cookie->domain())->toBe('otsch.codes');
});

test('It parses an expires attribute when included', function () {
    $cookie = new Cookie(
        'https://www.otsch.codes/blog',
        'otschcodes_session=cook13; expires=Wed, 23 Feb 2022 10:13:41 GMT'
    );
    expect($cookie->expires())->toBeInstanceOf(Date::class);
    expect($cookie->expires()->dateTime()->format('Y-m-d H:i'))->toBe('2022-02-23 10:13'); // @phpstan-ignore-line
});

test('It parses a maxAge attribute when included', function () {
    $cookie = new Cookie('https://www.otsch.codes/blog', 'otschcodes_session=cook13; Max-Age=600');
    expect($cookie->maxAge())->toBeInt();
    expect($cookie->maxAge())->toBe(600);
});

test('It parses a domain attribute when included', function () {
    $cookie = new Cookie('https://sub.domain.example.com/foobar', 'fookie=cook13; domain=domain.example.com');
    expect($cookie->domain())->toBe('domain.example.com');
});

test('It\'s not allowed to set a different domain than the one of the document url it was received from', function () {
    $cookie = new Cookie('https://sub.domain.example.com/foobar', 'fookie=cook13; domain=crwl.io');
})->throws(InvalidCookieException::class);

test('It\'s not allowed to set a subdomain that is not included in the document url it was received from', function () {
    $cookie = new Cookie('https://sub.domain.example.com/foobar', 'fookie=cook13; domain=foo.example.com');
})->throws(InvalidCookieException::class);

test('It parses a path attribute when included', function () {
    $cookie = new Cookie('https://sub.domain.example.com/foobar', 'co=asdf2345; path=/foobar');
    expect($cookie->path())->toBe('/foobar');
});

test('It parses a secure attribute when included', function () {
    $cookie = new Cookie('https://sub.domain.example.com/foobar', 'co=asdf2345; Secure');
    expect($cookie->secure())->toBeTrue();
});

test(
    'It throws an exception when secure attribute is sent but url where it was received from is not on https',
    function () {
        $cookie = new Cookie('http://www.example.io/foobar', 'eggs=ample; Secure');
    }
)->throws(InvalidCookieException::class);

test('It parses a SameSite attribute when included', function ($value) {
    $cookie = new Cookie('https://www.example.io/foobar', 'eggs=ample; SameSite=' . $value);
    expect($cookie->sameSite())->toBe($value);
})->with(['Strict', 'Lax', 'None']);

test('It throws an error when an unknown value is sent for the SameSite attribute', function () {
    new Cookie('https://www.example.io/foobar', 'eggs=ample; SameSite=Foo');
})->throws(InvalidCookieException::class);

test('It parses an HttpOnly attribute when included', function () {
    $cookie = new Cookie('https://jobs.foo.bar/', 'csrf=asdfjkloe123; HttpOnly');
    expect($cookie->httpOnly())->toBeTrue();
});

test(
    'It throws an Exception when cookie name is prefixed with __Secure- or __Host- and not sent via https',
    function ($prefix) {
        new Cookie('http://example.com', $prefix . 'Abc=defg123; Secure');
    }
)->with(['__Secure-', '__Host-'])->throws(InvalidCookieException::class);

test(
    'It throws an Exception when cookie name is prefixed with __Secure- or __Host- and Secure flag is not included',
    function ($prefix) {
        new Cookie('https://example.com', $prefix . 'Abc=defg123;');
    }
)->with(['__Secure-', '__Host-'])->throws(InvalidCookieException::class);

test('Using __Secure- prefix works when received via https and Secure flag is included', function () {
    $cookie = new Cookie('https://www.crwl.io', '__Secure-Foo=bar123; Secure');
    expect($cookie->hasSecurePrefix())->toBeTrue();
});

test('It throws an Exception when __Host- prefix used and Domain attribute included', function () {
    new Cookie('https://www.crwlr.software/', '__Host-Foo=bar123; Secure; Domain=www.crwlr.software; Path=/');
})->throws(InvalidCookieException::class);

test('It throws an Exception when __Host- prefix used and Path attribute is not included', function () {
    new Cookie('https://www.crwlr.software/', '__Host-Foo=bar123; Secure;');
})->throws(InvalidCookieException::class);

test('It throws an Exception when __Host- prefix used and Path attribute is not "/"', function () {
    new Cookie('https://www.crwlr.software/', '__Host-Foo=bar123; Secure; Path=/foo');
})->throws(InvalidCookieException::class);

test('Using __Host- works when everything is valid', function () {
    $cookie = new Cookie('https://www.crwlr.software/', '__Host-Foo=bar123; Secure; Path=/');
    expect($cookie->hasHostPrefix())->toBeTrue();
});
