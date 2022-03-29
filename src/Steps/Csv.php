<?php

namespace Crwlr\Crawler\Steps;

use Crwlr\Crawler\Aggregates\RequestResponseAggregate;
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
    final public function __construct(protected array $columnMapping, protected bool $skipFirstLine = false)
    {
    }

    /**
     * @param array<string|null> $columnMapping
     */
    public static function parseString(array $columnMapping, bool $skipFirstLine = false): static
    {
        return new static($columnMapping, $skipFirstLine);
    }

    /**
     * @param array<string|null> $columnMapping
     */
    public static function parseFile(array $columnMapping, bool $skipFirstLine = false): static
    {
        $instance = new static($columnMapping, $skipFirstLine);

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

    protected function validateAndSanitizeInput(mixed $input): mixed
    {
        if ($this->method === 'string') {
            if ($input instanceof RequestResponseAggregate) {
                return $input->response->getBody()->getContents();
            }

            return $this->validateAndSanitizeStringOrStringable(
                $input,
                'Input has to be string, stringable or RequestResponseAggregate'
            );
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

    private function validateAndSanitizeStringOrStringable(
        mixed $value,
        string $exceptionMessage = 'Input has to be string or stringable'
    ): string {
        if (is_object($value) && method_exists($value, '__toString')) {
            return $value->__toString();
        }

        if (is_string($value)) {
            return $value;
        }

        throw new InvalidArgumentException($exceptionMessage);
    }

    private function readFile(string $filePath): Generator
    {
        $handle = fopen($filePath, 'r');

        if ($handle === false) {
            return;
        }

        while (($row = fgetcsv($handle, 0, $this->separator, $this->enclosure, $this->escape)) !== false) {
            if ($this->skipFirstLine && !isset($isNotFirstLine)) {
                $isNotFirstLine = true;

                continue;
            }

            yield $this->mapRow($row);
        }

        fclose($handle);
    }

    /**
     * @param string[] $lines
     * @return Generator
     */
    private function mapLines(array $lines): Generator
    {
        foreach ($lines as $key => $line) {
            if ($key === 0 && $this->skipFirstLine) {
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
    private function mapRow(array $row): array
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
