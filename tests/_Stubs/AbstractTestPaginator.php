<?php

namespace tests\_Stubs;

use Crwlr\Crawler\Steps\Loading\Http\AbstractPaginator;
use Crwlr\Crawler\Steps\Loading\Http\Paginator;
use Psr\Http\Message\RequestInterface;

class AbstractTestPaginator extends AbstractPaginator
{
    public function __construct(
        int $maxPages = Paginator::MAX_PAGES_DEFAULT,
        private readonly string $nextUrl = 'https://www.example.com/bar',
    ) {
        parent::__construct($maxPages);
    }

    public function getNextUrl(): ?string
    {
        return $this->nextUrl;
    }

    /**
     * @return array<string, true>
     */
    public function getLoaded(): array
    {
        return $this->loaded;
    }

    public function getLoadedCount(): int
    {
        return $this->loadedCount;
    }

    public function getLatestRequest(): ?RequestInterface
    {
        return $this->latestRequest;
    }

    public function limitReached(): bool
    {
        return $this->maxPagesReached();
    }

    public function setFinished(): AbstractPaginator
    {
        return parent::setFinished();
    }
}
