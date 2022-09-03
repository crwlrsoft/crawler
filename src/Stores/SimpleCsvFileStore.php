<?php

namespace Crwlr\Crawler\Stores;

use Crwlr\Crawler\Result;
use Exception;

class SimpleCsvFileStore extends Store
{
    private int $createTimestamp;

    private bool $isFirstResult = true;

    public function __construct(private readonly string $storePath, private readonly ?string $filePrefix = null)
    {
        $this->createTimestamp = time();

        touch($this->filePath());
    }

    /**
     * @throws Exception
     */
    public function store(Result $result): void
    {
        $fileHandle = fopen($this->filePath(), 'a');

        if (!is_resource($fileHandle)) {
            throw new Exception('Failed to open file to store data');
        }

        if ($this->isFirstResult) {
            fputcsv($fileHandle, array_keys($result->toArray()));

            $this->isFirstResult = false;
        }

        fputcsv($fileHandle, array_values($result->toArray()));

        fclose($fileHandle);
    }

    public function filePath(): string
    {
        return $this->storePath . '/' .
            ($this->filePrefix ? $this->filePrefix . '-' : '') . $this->createTimestamp . '.csv';
    }
}
