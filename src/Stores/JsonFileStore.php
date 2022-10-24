<?php

namespace Crwlr\Crawler\Stores;

use Crwlr\Crawler\Result;
use Exception;

class JsonFileStore extends Store
{
    private int $createTimestamp;

    public function __construct(private readonly string $storePath, private readonly ?string $filePrefix = null)
    {
        $this->createTimestamp = time();

        touch($this->filePath());

        file_put_contents($this->filePath(), '[]');
    }

    /**
     * @throws Exception
     */
    public function store(Result $result): void
    {
        $currentResultsFileContent = file_get_contents($this->filePath());

        if (!$currentResultsFileContent) {
            $currentResultsFileContent = '[]';
        }

        $results = json_decode($currentResultsFileContent, true);

        $results[] = $result->toArray();

        file_put_contents($this->filePath(), json_encode($results));
    }

    public function filePath(): string
    {
        return $this->storePath . '/' .
          ($this->filePrefix ? $this->filePrefix . '-' : '') . $this->createTimestamp . '.json';
    }
}
