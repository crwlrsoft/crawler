<?php

namespace Crwlr\Crawler;

use AppendIterator;
use Crwlr\Crawler\Exceptions\MissingLoaderException;
use Crwlr\Crawler\Exceptions\MissingUserAgentException;
use Crwlr\Crawler\Loader\LoaderInterface;
use Crwlr\Crawler\Logger\CliLogger;
use Crwlr\Crawler\Steps\GroupInterface;
use Crwlr\Crawler\Steps\StepInterface;
use Generator;
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

    public function addGroup(GroupInterface $group): static
    {
        $this->addStep($group);

        return $this;
    }

    /**
     * @param mixed $input
     * @return Generator<Result>
     */
    public function run(mixed $input): Generator
    {
        $inputs = $this->prepareInput($input);

        foreach ($this->steps as $step) {
            $nextIterationInputs = new AppendIterator();

            foreach ($inputs as $input) {
                if ($input instanceof Output) {
                    $input = new Input($input);
                }

                $nextIterationInputs->append($step->invokeStep($input));
            }

            if ($step !== end($this->steps)) {
                $inputs = $nextIterationInputs;
            } else {
                $outputs = $nextIterationInputs;
            }
        }

        if (isset($outputs) && $outputs instanceof AppendIterator) {
            yield from $this->returnResults($outputs);
        }
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
     * @param AppendIterator<Output> $outputs
     * @return Generator<Result>
     */
    private function returnResults(AppendIterator $outputs): Generator
    {
        if ($this->anyResultResourcesDefinedInSteps()) {
            $results = [];

            foreach ($outputs as $output) {
                if ($output->result !== null && !in_array($output->result, $results, true)) {
                    $results[] = $output->result;
                }
            }

            // yield results only when iterated over final outputs, because that could still add properties to result
            // resources.
            foreach ($results as $result) {
                yield $result;
            }
        } else {
            foreach ($outputs as $output) {
                $result = new Result();
                $result->set('unnamed', $output->get());

                yield $result;
            }
        }
    }

    private function anyResultResourcesDefinedInSteps(): bool
    {
        foreach ($this->steps as $step) {
            if ($step->resultDefined()) {
                return true;
            }
        }

        return false;
    }
}
