<?php

namespace Crwlr\Crawler\Steps;

use Crwlr\Crawler\Input;
use Crwlr\Crawler\Output;
use Crwlr\Crawler\Result;
use Psr\Log\LoggerInterface;

abstract class Step implements StepInterface
{
    protected LoggerInterface $logger;
    private ?string $resultResourceName = null;
    private ?string $resultResourcePropertyName = null;

    final public function invokeStep(Input $input): array
    {
        $validInput = new Input($this->validateAndSanitizeInput($input), $input->result);

        return $this->invoke($validInput);
    }

    public function validateAndSanitizeInput(Input $input): mixed
    {
        return $input->get();
    }

    final public function addLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }

    public function initResultResource(string $resultResourceName): static
    {
        $this->resultResourceName = $resultResourceName;

        return $this;
    }

    public function resultResourceProperty(string $propertyName): static
    {
        $this->resultResourcePropertyName = $propertyName;

        return $this;
    }

    protected function output(mixed $values, Input $input): array
    {
        $outputs = [];
        $values = is_array($values) ? $values : [$values];

        foreach ($values as $value) {
            if ($this->resultResourceName) {
                $result = new Result($this->resultResourceName);
                $result->setProperty($this->resultResourcePropertyName, $value);
                $outputs[] = new Output($value, $result);
            } else {
                if ($this->resultResourcePropertyName) {
                    $input->result->setProperty($this->resultResourcePropertyName, $value);
                }

                $outputs[] = new Output($value, $input->result);
            }
        }

        return $outputs;
    }
}
