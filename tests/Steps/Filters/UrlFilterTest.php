<?php

namespace tests\Steps\Filters;

use Crwlr\Crawler\Steps\Filters\Enums\UrlFilterRule;
use Crwlr\Crawler\Steps\Filters\UrlFilter;

use function tests\helper_getStdClassWithData;

it('evaluates an url', function () {
    $urlFilter = new UrlFilter(UrlFilterRule::Domain, 'crwlr.software');

    expect($urlFilter->evaluate('https://www.crwlr.software/packages'))->toBeTrue();

    expect($urlFilter->evaluate('https://www.example.com/something'))->toBeFalse();
});

it('evaluates an url from an array using a key', function () {
    $urlFilter = (new UrlFilter(UrlFilterRule::Scheme, 'https'))->useKey('bar');

    expect($urlFilter->evaluate(['foo' => 'yo', 'bar' => 'https://www.example.com']))->toBeTrue();

    expect($urlFilter->evaluate(['foo' => 'yo', 'bar' => 'http://www.example.com']))->toBeFalse();
});

it('evaluates a string from an object using a key', function () {
    $urlFilter = (new UrlFilter(UrlFilterRule::PathStartsWith, '/foo'))->useKey('bar');

    expect($urlFilter->evaluate(
        helper_getStdClassWithData(['foo' => 'yo', 'bar' => 'https://www.example.com/foo/bar/baz']),
    ))->toBeTrue();

    expect($urlFilter->evaluate(
        helper_getStdClassWithData(['foo' => 'yo', 'bar' => 'https://www.example.com/articles/1']),
    ))->toBeFalse();
});

it('doesnt throw an exception when value is not a valid url', function () {
    $urlFilter = new UrlFilter(UrlFilterRule::Host, 'invalid');

    expect($urlFilter->evaluate('https*://invalid'))->toBeFalse();
});
