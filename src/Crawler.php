<?php

namespace Crwlr\Crawler;

use AppendIterator;
use Crwlr\Crawler\Loader\LoaderInterface;
use Crwlr\Crawler\Logger\CliLogger;
use Crwlr\Crawler\Steps\Group;
use Crwlr\Crawler\Steps\Loop;
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
    protected mixed $inputs = [];

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

    public static function loop(StepInterface $step): Loop
    {
        return new Loop($step);
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
        $this->inputs[] = $input;

        return $this;
    }

    /**
     * @param mixed[] $inputs
     */
    public function inputs(array $inputs): static
    {
        $this->inputs = array_merge($this->inputs, $inputs);

        return $this;
    }

    /**
     * @param string|StepInterface $stepOrResultKey
     * @param StepInterface|null $step
     * @return $this
     * @throws InvalidArgumentException
     */
    public function addStep(string|StepInterface $stepOrResultKey, ?StepInterface $step = null): static
    {
        if (is_string($stepOrResultKey) && $step === null) {
            throw new InvalidArgumentException('No StepInterface object provided');
        } elseif (is_string($stepOrResultKey)) {
            $step->setResultKey($stepOrResultKey);
        } else {
            $step = $stepOrResultKey;
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

        $this->inputs = [];
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
        return array_map(function ($input) {
            return new Input($input);
        }, $this->inputs);
    }

    /**
     * @param AppendIterator<Output> $outputs
     * @return Generator<Result>
     */
    private function returnResults(AppendIterator $outputs): Generator
    {
        if ($this->anyResultKeysDefinedInSteps()) {
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

    private function anyResultKeysDefinedInSteps(): bool
    {
        foreach ($this->steps as $step) {
            if ($step->getResultKey() !== null) {
                return true;
            }
        }

        return false;
    }
}
