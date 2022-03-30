<?php

namespace tests\Loader\Http\Cookies;

use Crwlr\Crawler\Exceptions\InvalidCookieException;
use Crwlr\Crawler\Loader\Http\Cookies\Cookie;
use Crwlr\Crawler\Loader\Http\Cookies\Date;
use Crwlr\Url\Url;
use DateInterval;
use DateTime;
use DateTimeInterface;
use DateTimeZone;
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

test('The __toString() method returns name=value (only)', function () {
    $cookie = new Cookie('https://www.crwl.io', '__Secure-cook13N4m3=c00k1eV4lu3; Secure; Path=/');
    expect($cookie->__toString())->toBe('__Secure-cook13N4m3=c00k1eV4lu3');
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
        'otschcodes_session=cook13; Expires=Wed, 23-Feb-2022 10:13:41 GMT'
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
    new Cookie('https://sub.domain.example.com/foobar', 'fookie=cook13; domain=crwl.io');
})->throws(InvalidCookieException::class);

test('It\'s not allowed to set a subdomain that is not included in the document url it was received from', function () {
    new Cookie('https://sub.domain.example.com/foobar', 'fookie=cook13; domain=foo.example.com');
})->throws(InvalidCookieException::class);

test('When domain attribute is defined with leading dot, it\'s ignored', function () {
    $cookie = new Cookie('https://sub.domain.example.com/', 'fookie=cook13; domain=.domain.example.com');
    expect($cookie->domain())->toBe('domain.example.com');
});

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
        new Cookie('http://www.example.io/foobar', 'eggs=ample; Secure');
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

test('It\'s possible to set multiple attributes', function () {
    $cookie = new Cookie(
        'https://www.crwl.io',
        '__Secure-cook13N4m3=c00k1eV4lu3; Expires=Wed, 23-Feb-2022 10:13:41 GMT; Secure; Path=/foo'
    );
    expect($cookie->secure())->toBeTrue();
    expect($cookie->expires()?->dateTime()->format('d.m.Y H:i'))->toBe('23.02.2022 10:13');
    expect($cookie->path())->toBe('/foo');
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

test(
    'It should not be sent to a url when the domain doesn\'t match',
    function ($receivedFrom, $domainAttribute, $shouldBeSentTo) {
        $cookie = new Cookie($receivedFrom, 'cookie=value' . ($domainAttribute ? '; Domain=' . $domainAttribute : ''));
        expect($cookie->shouldBeSentTo($shouldBeSentTo))->toBeFalse();
    }
)->with([
    ['https://www.crwlr.software', null, 'https://www.otsch.codes'],
    ['https://www.crwlr.software', 'www.crwlr.software', 'https://jobs.crwlr.software'],
    ['https://www.crwlr.software', 'www.crwlr.software', 'https://crwlr.software'],
    ['https://sub.domain.crwlr.software', 'sub.domain.crwlr.software', 'https://sab.domain.crwlr.software'],
    ['https://sub.domain.crwlr.software', 'sub.domain.crwlr.software', 'https://domain.crwlr.software'],
]);

test('It should be sent to a url when the domain matches', function ($receivedFrom, $domainAttribute, $shouldBeSentTo) {
    $cookie = new Cookie($receivedFrom, 'cookie=value' . ($domainAttribute ? '; Domain=' . $domainAttribute : ''));
    expect($cookie->shouldBeSentTo($shouldBeSentTo))->toBeTrue();
})->with([
    ['https://www.crwlr.software', null, 'https://www.crwlr.software'],
    ['https://www.crwlr.software', null, 'https://crwlr.software'],
    ['https://www.crwlr.software', null, 'https://anything.crwlr.software'],
    ['https://sub.domain.crwlr.software', 'domain.crwlr.software', 'https://domain.crwlr.software'],
    ['https://sub.domain.crwlr.software', 'domain.crwlr.software', 'https://sab.domain.crwlr.software'],
]);

test(
    'It should not be sent to a url when it has a __Host- prefix and hosts don\'t match exactly',
    function ($receivedFrom, $shouldBeSentTo) {
        $cookie = new Cookie($receivedFrom, '__Host-cookie=value; Secure; Path=/');
        expect($cookie->shouldBeSentTo($shouldBeSentTo))->toBeFalse();
    }
)->with([
    ['https://www.crwlr.software', 'https://jobs.crwlr.software'],
    ['https://sub.domain.crwlr.software', 'https://domain.crwlr.software'],
    ['https://subdomain.crwlr.software', 'https://sabdomain.crwlr.software'],
]);

test('It should not be sent to non https url when secure flag is included', function () {
    $cookie = new Cookie('https://www.crwl.io', 'cookie=value; Secure');
    expect($cookie->shouldBeSentTo('http://www.crwl.io'))->toBeFalse();
});

test('It should be sent to https url when secure flag is included', function () {
    $cookie = new Cookie('https://www.crwl.io', 'cookie=value; Secure');
    expect($cookie->shouldBeSentTo('https://www.crwl.io'))->toBeTrue();
});

test('It should be sent to non https url when secure flag is included but host is localhost', function ($host) {
    $cookie = new Cookie('https://' . $host, 'cookie=value; Secure');
    expect($cookie->shouldBeSentTo('http://' . $host))->toBeTrue();
})->with(['localhost', '127.0.0.1']);

test(
    'It should not be sent to urls where the path doesn\'t match the sent path attribute',
    function ($path, $shouldBeSentTo) {
        $cookie = new Cookie('https://www.crwlr.software', 'cookie=value; Path=' . $path);
        expect($cookie->shouldBeSentTo('https://www.crwlr.software' . $shouldBeSentTo))->toBeFalse();
    }
)->with([
    ['/foo', '/bar'],
    ['/foo', '/foobar'],
    ['/foo', '/'],
    ['/foo', '/bar/foo'],
]);

test(
    'It should be sent to urls where the path does match the sent path attribute',
    function ($path, $shouldBeSentTo) {
        $cookie = new Cookie('https://www.crwlr.software', 'cookie=value; Path=' . $path);
        expect($cookie->shouldBeSentTo('https://www.crwlr.software' . $shouldBeSentTo))->toBeTrue();
    }
)->with([
    ['/', '/anything'],
    ['/foo', '/foo'],
    ['/foo', '/foo/something'],
    ['/foo', '/foo/some/thing'],
]);

test('It should not be sent when already expired', function () {
    $now = new DateTime('now', new DateTimeZone('GMT'));
    $now = $now->sub(new DateInterval('PT1S'));
    $cookie = new Cookie(
        'https://www.crwlr.software',
        'cookie=value; Expires=' . $now->format(DateTimeInterface::COOKIE)
    );
    expect($cookie->shouldBeSentTo('https://www.crwlr.software'))->toBeFalse();
});

test('It should be sent when date of expires attribute is not reached', function () {
    $now = new DateTime('now', new DateTimeZone('GMT'));
    $now = $now->add(new DateInterval('PT5S'));
    $cookie = new Cookie(
        'https://www.crwlr.software',
        'cookie=value; Expires=' . $now->format(DateTimeInterface::COOKIE)
    );
    expect($cookie->shouldBeSentTo('https://www.crwlr.software'))->toBeTrue();
});

test('It should not be sent when maxAge attribute is already reached', function () {
    $cookie = new Cookie('https://www.crwlr.software', 'cookie=value; Max-Age=1');
    expect($cookie->shouldBeSentTo('https://www.crwlr.software'))->toBeTrue();
    invade($cookie)->receivedAtTimestamp -= 2; // instead of sleep, manipulate the timestamp when it was received.
    expect($cookie->shouldBeSentTo('https://www.crwlr.software'))->toBeFalse();
});

test('It is immediately expired when the max-age attribute is zero or negative', function ($maxAgeValue) {
    $cookie = new Cookie('https://www.crwlr.software', 'cookie=value; Max-Age=' . $maxAgeValue);
    expect($cookie->shouldBeSentTo('https://www.crwlr.software'))->toBeFalse();
})->with([0, -1, -5, -1000]);

test('It should be sent when maxAge attribute is not yet reached', function () {
    $cookie = new Cookie('https://www.crwlr.software', 'cookie=value; Max-Age=1');
    expect($cookie->shouldBeSentTo('https://www.crwlr.software'))->toBeTrue();
});
