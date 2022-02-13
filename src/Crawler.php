<?php

namespace Crwlr\Crawler;

use Crwlr\Crawler\Exceptions\MissingLoaderException;
use Crwlr\Crawler\Exceptions\MissingUserAgentException;
use Crwlr\Crawler\Loader\LoaderInterface;
use Crwlr\Crawler\Logger\CliLogger;
use Crwlr\Crawler\Steps\Group;
use Crwlr\Crawler\Steps\GroupInterface;
use Crwlr\Crawler\Steps\StepInterface;
use Psr\Log\LoggerInterface;

class Crawler
{
    protected ?UserAgent $userAgent = null;
    protected ?LoaderInterface $loader = null;
    protected ?LoggerInterface $logger = null;

    /**
     * @var array|StepInterface[]
     */
    protected array $steps = [];

    public function setUserAgent(UserAgent|string $userAgent): void
    {
        $this->userAgent = is_string($userAgent) ? new UserAgent($userAgent) : $userAgent;
    }

    /**
     * @throws MissingUserAgentException
     */
    public function userAgent(): UserAgent
    {
        if (!$this->userAgent) {
            throw new MissingUserAgentException('You must set a UserAgent.');
        }

        return $this->userAgent;
    }

    public function setLoader(LoaderInterface $loader): void
    {
        $this->loader = $loader;
    }

    /**
     * @throws MissingLoaderException
     */
    public function loader(): LoaderInterface
    {
        if (!$this->loader) {
            throw new MissingLoaderException('You must set a Loader.');
        }

        return $this->loader;
    }

    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }

    public function logger(): LoggerInterface
    {
        if (!$this->logger) {
            $this->logger = new CliLogger();
        }

        return $this->logger;
    }

    public function addStep(StepInterface $step): static
    {
        $step->addLogger($this->logger());

        if (method_exists($step, 'addLoader')) {
            $step->addLoader($this->loader());
        }

        $this->steps[] = $step;

        return $this;
    }

    public function addGroup(?GroupInterface $group = null): GroupInterface
    {
        if (!$group) {
            $group = new Group();
        }

        $this->addStep($group);

        return $group;
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
     * @param mixed $input
     * @return Input[]
     */
    private function prepareInput(mixed $input): array
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
                if (!in_array($output->result, $results, true)) {
                    $results[] = $output->result;
                }
            }
        } else {
            foreach ($outputs as $output) {
                $result = new Result();
                $result->set('unnamed', $output->get());
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
