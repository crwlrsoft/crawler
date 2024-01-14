<?php

namespace Crwlr\Crawler\Steps\Loading\Http\Paginators;

use Crwlr\Crawler\Steps\Loading\Http;
use Crwlr\Crawler\Steps\Loading\Http\Paginator;
use Crwlr\Crawler\Steps\Loading\Http\Paginators\QueryParams\Decrementor;
use Crwlr\Crawler\Steps\Loading\Http\Paginators\QueryParams\Incrementor;
use Crwlr\Crawler\Steps\Loading\Http\Paginators\QueryParams\QueryParamManipulator;
use Crwlr\QueryString\Query;
use Crwlr\Url\Url;
use Exception;
use GuzzleHttp\Psr7\Utils;
use Psr\Http\Message\RequestInterface;

class QueryParamsPaginator extends Http\AbstractPaginator
{
    /**
     * @var QueryParamManipulator[]
     */
    protected array $manipulators = [];

    /**
     * @var bool True means the class handles URL query params, false means it's about params sent as request body.
     */
    protected bool $paramsInUrl = true;

    public static function paramsInUrl(int $maxPages = Paginator::MAX_PAGES_DEFAULT): self
    {
        return new self($maxPages);
    }

    public function inUrl(): self
    {
        $this->paramsInUrl = true;

        return $this;
    }

    public static function paramsInBody(int $maxPages = Paginator::MAX_PAGES_DEFAULT): self
    {
        $instance = new self($maxPages);

        $instance->paramsInUrl = false;

        return $instance;
    }

    public function inBody(): self
    {
        $this->paramsInUrl = false;

        return $this;
    }

    public function increase(string $queryParamName, int $by = 1, bool $useDotNotation = false): self
    {
        $this->manipulators[] = new Incrementor($queryParamName, $by, $useDotNotation);

        return $this;
    }

    public function increaseUsingDotNotation(string $queryParamName, int $by = 1): self
    {
        $this->manipulators[] = new Incrementor($queryParamName, $by, true);

        return $this;
    }

    public function decrease(string $queryParamName, int $by = 1, bool $useDotNotation = false): self
    {
        $this->manipulators[] = new Decrementor($queryParamName, $by, $useDotNotation);

        return $this;
    }

    public function decreaseUsingDotNotation(string $queryParamName, int $by = 1): self
    {
        $this->manipulators[] = new Decrementor($queryParamName, $by, true);

        return $this;
    }

    /**
     * @throws Exception
     */
    public function getNextRequest(): ?RequestInterface
    {
        if (!$this->latestRequest) {
            return null;
        }

        if ($this->paramsInUrl) {
            $url = Url::parse($this->latestRequest->getUri());

            $query = $url->queryString();
        } else {
            $query = Query::fromString(Http::getBodyString($this->latestRequest));
        }

        foreach ($this->manipulators as $manipulator) {
            $query = $manipulator->execute($query);
        }

        if ($this->paramsInUrl) {
            $request = $this->latestRequest->withUri($url->toPsr7());
        } else {
            $request = $this->latestRequest->withBody(Utils::streamFor($query->toString()));
        }

        return $request;
    }
}
