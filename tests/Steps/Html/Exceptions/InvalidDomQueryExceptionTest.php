<?php

namespace tests\Steps\Html\Exceptions;

use Crwlr\Crawler\Steps\Html\Exceptions\InvalidDomQueryException;
use Symfony\Component\CssSelector\Exception\ExpressionErrorException;
use Symfony\Component\CssSelector\Exception\SyntaxErrorException;

it('can be created from a symfony ExpressionErrorException', function () {
    $exception = InvalidDomQueryException::fromSymfonyException('.foo:before', new ExpressionErrorException('error'));

    expect($exception->getDomQuery())
        ->toBe('.foo:before')
        ->and($exception->getMessage())
        ->toBe('error');
});

it('can be created from a symfony SyntaxErrorException', function () {
    $exception = InvalidDomQueryException::fromSymfonyException('.foo;', new SyntaxErrorException('error message'));

    expect($exception->getDomQuery())
        ->toBe('.foo;')
        ->and($exception->getMessage())
        ->toBe('error message');
});

it('can be created from a message and a query', function () {
    $exception = InvalidDomQueryException::make('message', '.foo > .bar;');

    expect($exception->getDomQuery())
        ->toBe('.foo > .bar;')
        ->and($exception->getMessage())
        ->toBe('message');
});
