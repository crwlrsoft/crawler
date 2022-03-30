<?php

namespace Crwlr\Crawler\Stores;

use Crwlr\Crawler\Result;
use Exception;

class SimpleCsvFileStore implements StoreInterface
{
    private int $createTimestamp;

    private bool $isFirstResult = true;

    public function __construct(private string $storePath, private ?string $filePrefix = null)
    {
        $this->createTimestamp = time();

        touch($this->filePath());
    }

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
