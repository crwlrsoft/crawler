<?php

namespace Crwlr\Crawler;

use Crwlr\Crawler\Loader\LoaderInterface;
use Crwlr\Crawler\Logger\CliLogger;
use Crwlr\Crawler\Steps\Group;
use Crwlr\Crawler\Steps\Loop;
use Crwlr\Crawler\Steps\StepInterface;
use Crwlr\Crawler\Stores\StoreInterface;
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

    private ?StoreInterface $store = null;

    private bool|int $monitorMemoryUsage = false;

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

    public static function setMemoryLimit(string $memoryLimit): false|string
    {
        return ini_set('memory_limit', $memoryLimit);
    }

    public static function getMemoryLimit(): false|string
    {
        return ini_get('memory_limit');
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

    public function setStore(StoreInterface $store): static
    {
        $store->addLogger($this->logger);

        $this->store = $store;

        return $this;
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
     * Run the crawler and traverse results
     *
     * When you've set a store, or you just don't need the results for any other reason (e.g. you use the crawler for
     * cache warming) where you're calling the crawler, use this method.
     *
     * @throws Exception
     */
    public function runAndTraverse(): void
    {
        foreach ($this->run() as $result) {
        }
    }

    /**
     * Run the Crawler
     *
     * Handles calling all the steps and cascading the data from step to step.
     * It returns a Generator, so when using this method directly, you need to traverse the Generator, otherwise nothing
     * happens. Alternatively you can use runAndTraverse().
     *
     * @return Generator<Result>
     * @throws Exception
     */
    public function run(): Generator
    {
        $inputs = $this->prepareInput();

        if ($this->firstStep()) {
            foreach ($inputs as $input) {
                yield from $this->storeAndReturnResults($this->invokeStepsRecursive($input, $this->firstStep(), 0));
            }
        }

        $this->reset();
    }

    /**
     * Use this method if you want the crawler to add log messages with the current memory usage after every step
     * invocation.
     *
     * @param int|null $ifAboveXBytes  You can provide an int of bytes as a limit above which the crawler should log
     *                                 the usage.
     */
    public function monitorMemoryUsage(?int $ifAboveXBytes = null): static
    {
        $this->monitorMemoryUsage = $ifAboveXBytes ?? true;

        return $this;
    }

    protected function logger(): LoggerInterface
    {
        return new CliLogger();
    }

    /**
     * @return Generator<Output>
     */
    private function invokeStepsRecursive(Input $input, StepInterface $step, int $stepIndex): Generator
    {
        $outputs = $step->invokeStep($input);

        if ($step->cascades() && $this->nextStep($stepIndex)) {
            foreach ($outputs as $output) {
                if ($this->monitorMemoryUsage !== false) {
                    $this->logMemoryUsage();
                }

                if ($this->nextStep($stepIndex)) {
                    yield from $this->invokeStepsRecursive(
                        new Input($output),
                        $this->nextStep($stepIndex),
                        $stepIndex + 1
                    );
                }
            }
        } elseif ($step->cascades()) {
            yield from $outputs;
        }
    }

    /**
     * @param Generator<Output> $outputs
     * @return Generator<Result>
     */
    private function storeAndReturnResults(Generator $outputs): Generator
    {
        if ($this->anyResultKeysDefinedInSteps()) {
            yield from $this->storeAndReturnDefinedResults($outputs);
        } else {
            yield from $this->storeAndReturnOutputsAsResults($outputs);
        }
    }

    /**
     * @param Generator<Output> $outputs
     * @return Generator<Result>
     */
    private function storeAndReturnDefinedResults(Generator $outputs): Generator
    {
        $results = [];

        foreach ($outputs as $output) {
            if ($output->result !== null && !in_array($output->result, $results, true)) {
                $results[] = $output->result;
            }
        }

        // yield results only after iterating over final outputs, because that could still add properties to result
        // resources.
        foreach ($results as $result) {
            $this->store?->store($result);

            yield $result;
        }
    }

    /**
     * @param Generator<Output> $outputs
     * @return Generator<Result>
     */
    private function storeAndReturnOutputsAsResults(Generator $outputs): Generator
    {
        foreach ($outputs as $output) {
            $result = (new Result())->set('unnamed', $output->get());

            $this->store?->store($result);

            yield $result;
        }
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

    private function anyResultKeysDefinedInSteps(): bool
    {
        foreach ($this->steps as $step) {
            if ($step->addsToOrCreatesResult()) {
                return true;
            }
        }

        return false;
    }

    private function logMemoryUsage(): void
    {
        $memoryUsage = memory_get_usage();

        if (!is_int($this->monitorMemoryUsage) || $memoryUsage > $this->monitorMemoryUsage) {
            $this->logger->info('memory usage: ' . $memoryUsage);
        }
    }

    private function firstStep(): ?StepInterface
    {
        return $this->steps[0] ?? null;
    }

    private function nextStep(int $afterIndex): ?StepInterface
    {
        return $this->steps[$afterIndex + 1] ?? null;
    }

    private function reset(): void
    {
        $this->inputs = [];

        foreach ($this->steps as $step) {
            $step->resetAfterRun();
        }
    }
}
