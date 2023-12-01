<?php

namespace Crwlr\Crawler\Steps\Html\Exceptions;

use Exception;
use Symfony\Component\CssSelector\Exception\ExpressionErrorException;
use Symfony\Component\CssSelector\Exception\SyntaxErrorException;

class InvalidDomQueryException extends Exception
{
    protected string $query = '';

    public static function make(string $message, string $domQuery): self
    {
        $exception = new self($message);

        $exception->setDomQuery($domQuery);

        return $exception;
    }

    public static function fromSymfonyException(
        string $domQuery,
        ExpressionErrorException|SyntaxErrorException $originalException,
    ): self {
        $exception = new self(
            $originalException->getMessage(),
            $originalException->getCode(),
            $originalException,
        );

        $exception->setDomQuery($domQuery);

        return $exception;
    }

    public function setDomQuery(string $domQuery): void
    {
        $this->query = $domQuery;
    }

    public function getDomQuery(): string
    {
        return $this->query;
    }
}
