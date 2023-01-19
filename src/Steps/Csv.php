<?php

namespace Crwlr\Crawler\Steps;

use Exception;
use Generator;
use InvalidArgumentException;

class Csv extends Step
{
    protected string $method = 'string';

    protected string $separator = ',';

    protected string $enclosure = '"';

    protected string $escape = '\\';

    /**
     * @param array<string|null> $columnMapping
     */
    public function __construct(protected array $columnMapping = [], protected bool $skipFirstLine = false)
    {
    }

    /**
     * @param array<string|null> $columnMapping
     */
    public static function parseString(array $columnMapping = [], bool $skipFirstLine = false): self
    {
        return new self($columnMapping, $skipFirstLine);
    }

    /**
     * @param array<string|null> $columnMapping
     */
    public static function parseFile(array $columnMapping = [], bool $skipFirstLine = false): self
    {
        $instance = new self($columnMapping, $skipFirstLine);

        $instance->method = 'file';

        return $instance;
    }

    public function skipFirstLine(): static
    {
        $this->skipFirstLine = true;

        return $this;
    }

    public function separator(string $separator): static
    {
        if (strlen($separator) > 1) {
            throw new InvalidArgumentException('CSV separator must be single character');
        }

        $this->separator = $separator;

        return $this;
    }

    public function enclosure(string $enclosure): static
    {
        $this->enclosure = $enclosure;

        return $this;
    }

    public function escape(string $escape): static
    {
        $this->escape = $escape;

        return $this;
    }

    protected function validateAndSanitizeInput(mixed $input): string
    {
        if ($this->method === 'string') {
            return $this->validateAndSanitizeStringOrHttpResponse($input);
        } elseif ($this->method === 'file') {
            return $this->validateAndSanitizeStringOrStringable($input);
        } else {
            throw new InvalidArgumentException('Parse CSV method must be string or file');
        }
    }

    /**
     * @param string $input
     * @throws Exception
     */
    protected function invoke(mixed $input): Generator
    {
        if ($this->method === 'file') {
            if (!file_exists($input)) {
                throw new Exception('CSV file not found');
            }

            yield from $this->readFile($input);
        } elseif ($this->method === 'string') {
            yield from $this->mapLines(explode(PHP_EOL, $input));
        }
    }

    protected function readFile(string $filePath): Generator
    {
        $handle = fopen($filePath, 'r');

        if ($handle === false) {
            return;
        }

        $isFirstLine = true;

        while (($row = fgetcsv($handle, 0, $this->separator, $this->enclosure, $this->escape)) !== false) {
            if ($isFirstLine) {
                if (empty($this->columnMapping)) {
                    $this->columnMapping = $row;
                }

                $isFirstLine = false;

                if ($this->skipFirstLine) {
                    continue;
                }
            }

            yield $this->mapRow($row);
        }

        fclose($handle);
    }

    /**
     * @param string[] $lines
     * @return Generator
     */
    protected function mapLines(array $lines): Generator
    {
        foreach ($lines as $key => $line) {
            if ($key === 0 && $this->skipFirstLine) {
                if (empty($this->columnMapping)) {
                    $this->columnMapping = str_getcsv($line, $this->separator, $this->enclosure, $this->escape);
                }

                continue;
            }

            if (!empty($line)) {
                yield $this->mapRow(str_getcsv($line, $this->separator, $this->enclosure, $this->escape));
            }
        }
    }

    /**
     * @param mixed[] $row
     * @return mixed[]
     */
    protected function mapRow(array $row): array
    {
        $count = 0;
        $mapped = [];

        foreach ($row as $column) {
            if (isset($this->columnMapping[$count]) && !empty($this->columnMapping[$count])) {
                $mapped[$this->columnMapping[$count]] = $column;
            }

            $count++;
        }

        return $mapped;
    }
}
