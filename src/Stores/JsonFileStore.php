<?php

namespace Crwlr\Crawler\Stores;

use Crwlr\Crawler\Result;
use Exception;

class JsonFileStore extends Store
{
    private int $createTimestamp;

    private bool $isFirstResult = true;

    public function __construct(private readonly string $storePath, private readonly ?string $filePrefix = null)
    {
        $this->createTimestamp = time();
    }

    /**
     * @throws Exception
     */
    public function store(Result $result): void
    {
        $resultArray = $result->toArray();
        if (!$this->isFirstResult) {
            $currentFile = file_get_contents($this->filePath());
            $tempArray = json_decode($currentFile, true);
            array_push($tempArray, $resultArray);
            $resultArray = $tempArray;
        } else {
            $this->isFirstResult = false;
        }
        file_put_contents($this->filePath(), json_encode([$resultArray]));
    }

    public function filePath(): string
    {
        return $this->storePath . '/' .
          ($this->filePrefix ? $this->filePrefix . '-' : '') . $this->createTimestamp . '.json';
    }
}
