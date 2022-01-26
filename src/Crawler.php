<?php

namespace Crwlr\Crawler;

use Crwlr\Crawler\Loader\LoaderInterface;
use Crwlr\Crawler\Logger\CliLogger;
use Crwlr\Crawler\Steps\Loading\LoadingStepInterface;
use Crwlr\Crawler\Steps\StepInterface;
use Psr\Log\LoggerInterface;

abstract class Crawler
{
    protected ?LoggerInterface $logger = null;
    protected ?LoaderInterface $loader = null;

    /**
     * @var array|StepInterface[]
     */
    protected array $steps = [];

    abstract public function userAgent(): UserAgent;

    /**
     * @return LoaderInterface
     */
    abstract public function loader(): LoaderInterface;

    public function addStep(StepInterface $step): void
    {
        $step->addLogger($this->logger());

        if ($step instanceof LoadingStepInterface) {
            $step->addLoader($this->loader());
        }

        $this->steps[] = $step;
    }

    public function logger(): LoggerInterface
    {
        if (!$this->logger) {
            $this->logger = new CliLogger();
        }

        return $this->logger;
    }

    /**
     * @param mixed $input
     * @return Results
     */
    public function run(mixed $input): Results
    {
        $inputs = $this->prepareInput($input);
        $outputs = [];

        foreach ($this->steps as $key => $step) {
            if ($key > 0) {
                $inputs = $this->outputsToInputs($outputs);
                $outputs = [];
            }

            foreach ($inputs as $input) {
                array_push($outputs, ...$step->invokeStep($input));
            }
        }

        return $this->returnResults($outputs);
    }

    /**
     * @param string|string[] $input
     * @return Input[]
     */
    private function prepareInput(string|array $input): array
    {
        if (!is_array($input)) {
            return [new Input($input)];
        }

        return array_map(function ($input) {
            return new Input($input);
        }, $input);
    }

    /**
     * @param array|Output[] $outputs
     * @return Input[]
     */
    private function outputsToInputs(array $outputs): array
    {
        return array_map(function ($output) {
            return new Input($output);
        }, $outputs);
    }

    /**
     * @param array|Output[] $outputs
     * @return Results
     */
    private function returnResults(array $outputs): Results
    {
        $results = [];

        if ($this->outputsContainResults($outputs)) {
            foreach ($outputs as $output) {
                if (!in_array($output->result, $results)) {
                    $results[] = $output->result;
                }
            }
        } else {
            foreach ($outputs as $output) {
                $result = new Result();
                $result->setProperty('unnamed', $output->get());
                $results[] = $result;
            }
        }

        return new Results($results);
    }

    /**
     * @param array|Output[] $outputs
     * @return bool
     */
    private function outputsContainResults(array $outputs): bool
    {
        foreach ($outputs as $output) {
            if ($output->result) {
                return true;
            }
        }

        return false;
    }
}
