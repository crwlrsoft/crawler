<?php

namespace Crwlr\Crawler;

use AppendIterator;
use Crwlr\Crawler\Loader\LoaderInterface;
use Crwlr\Crawler\Logger\CliLogger;
use Crwlr\Crawler\Steps\Group;
use Crwlr\Crawler\Steps\LoopStep;
use Crwlr\Crawler\Steps\StepInterface;
use Crwlr\Crawler\UserAgents\UserAgentInterface;
use Exception;
use Generator;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;

abstract class Crawler
{
    protected UserAgentInterface $userAgent;
    protected LoaderInterface $loader;
    protected LoggerInterface $logger;
    protected mixed $input = null;

    /**
     * @var array|StepInterface[]
     */
    protected array $steps = [];

    public function __construct()
    {
        $this->userAgent = $this->userAgent();
        $this->logger = $this->logger();
        $this->loader = $this->loader($this->userAgent, $this->logger);
    }

    abstract protected function userAgent(): UserAgentInterface;
    abstract protected function loader(UserAgentInterface $userAgent, LoggerInterface $logger): LoaderInterface;

    public static function loop(StepInterface $step): LoopStep
    {
        return new LoopStep($step);
    }

    public static function group(): Group
    {
        return new Group();
    }

    public function getUserAgent(): UserAgentInterface
    {
        return $this->userAgent;
    }

    public function getLogger(): LoggerInterface
    {
        return $this->logger;
    }

    public function getLoader(): LoaderInterface
    {
        return $this->loader;
    }

    public function input(mixed $input): static
    {
        $this->input = $input;

        return $this;
    }

    /**
     * @param string|StepInterface $stepOrResultPropertyName
     * @param StepInterface|null $step
     * @return $this
     * @throws InvalidArgumentException
     */
    public function addStep(string|StepInterface $stepOrResultPropertyName, ?StepInterface $step = null): static
    {
        if (is_string($stepOrResultPropertyName) && $step === null) {
            throw new InvalidArgumentException('No StepInterface object provided');
        } elseif (is_string($stepOrResultPropertyName)) {
            $step->resultResourceProperty($stepOrResultPropertyName);
        } else {
            $step = $stepOrResultPropertyName;
        }

        $step->addLogger($this->logger);

        if (method_exists($step, 'addLoader')) {
            $step->addLoader($this->loader);
        }

        $this->steps[] = $step;

        return $this;
    }

    /**
     * @return Generator<Result>
     * @throws Exception
     */
    public function run(): Generator
    {
        $inputs = $this->prepareInput();

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

    protected function logger(): LoggerInterface
    {
        return new CliLogger();
    }

    /**
     * @return Input[]
     * @throws Exception
     */
    private function prepareInput(): array
    {
        if ($this->input === null) {
            throw new Exception('No initial input');
        }

        if (!is_array($this->input)) {
            return [new Input($this->input)];
        }

        return array_map(function ($input) {
            return new Input($input);
        }, $this->input);
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
