<?php

namespace Crwlr\Crawler\Steps;

use AppendIterator;
use Closure;
use Crwlr\Crawler\Input;
use Crwlr\Crawler\Output;
use Generator;
use NoRewindIterator;
use Psr\Log\LoggerInterface;

final class LoopStep implements StepInterface
{
    private int $maxIterations = 1000;
    private null|Closure|StepInterface $transformer = null;

    public function __construct(private StepInterface $step)
    {
    }

    public function maxIterations(int $count): self
    {
        $this->maxIterations = $count;

        return $this;
    }

    public function invokeStep(Input $input): Generator
    {
        $inputs = [$input];
        $i = 0;

        while (!empty($inputs) && $i < $this->maxIterations) {
            $outputs = new AppendIterator();

            foreach ($inputs as $input) {
                $outputs->append(new NoRewindIterator($this->step->invokeStep($input)));
            }

            $inputs = [];

            foreach ($outputs as $output) {
                yield $output;

                $newInput = $this->outputToInput($output);

                if ($newInput !== null) {
                    $inputs[] = $newInput;
                }
            }

            $i++;
        }
    }

    public function useInputKey(string $key): static
    {
        $this->step->useInputKey($key);

        return $this;
    }

    public function transformOutputToInput(Closure|StepInterface $transformer): self
    {
        $this->transformer = $transformer;

        return $this;
    }

    public function addLogger(LoggerInterface $logger): static
    {
        $this->step->addLogger($logger);

        return $this;
    }

    public function setResultKey(string $key): static
    {
        $this->step->setResultKey($key);

        return $this;
    }

    public function getResultKey(): ?string
    {
        return $this->step->getResultKey();
    }

    private function outputToInput(Output $output): ?Input
    {
        if ($this->transformer) {
            if ($this->transformer instanceof Closure) {
                $transformerResult = $this->transformer->call($this->step, $output);
            } else {
                $transformerResult = $this->transformer->invokeStep(new Input($output))->current();
            }

            return $transformerResult === null ? null : new Input($transformerResult);
        }

        return new Input($output);
    }
}
