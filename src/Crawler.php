<?php

namespace Crwlr\Crawler;

use Closure;
use Crwlr\Crawler\Exceptions\UnknownLoaderKeyException;
use Crwlr\Crawler\Loader\AddLoadersToStepAction;
use Crwlr\Crawler\Loader\LoaderInterface;
use Crwlr\Crawler\Logger\CliLogger;
use Crwlr\Crawler\Steps\BaseStep;
use Crwlr\Crawler\Steps\Exceptions\PreRunValidationException;
use Crwlr\Crawler\Steps\Group;
use Crwlr\Crawler\Steps\Step;
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

    /**
     * @var LoaderInterface|array<string, LoaderInterface>
     */
    protected LoaderInterface|array $loader;

    protected LoggerInterface $logger;

    protected mixed $inputs = [];

    /**
     * @var array<int, StepInterface>
     */
    protected array $steps = [];

    protected ?StoreInterface $store = null;

    protected bool|int $monitorMemoryUsage = false;

    protected ?Closure $outputHook = null;

    public function __construct()
    {
        $this->userAgent = $this->userAgent();

        $this->logger = $this->logger();

        $this->loader = $this->loader($this->userAgent, $this->logger);
    }

    public function __clone(): void
    {
        $this->inputs = [];

        $this->steps = [];

        $this->store = null;

        $this->outputHook = null;
    }

    abstract protected function userAgent(): UserAgentInterface;

    /**
     * @param UserAgentInterface $userAgent
     * @param LoggerInterface $logger
     * @return LoaderInterface|array<string, LoaderInterface>
     */
    abstract protected function loader(UserAgentInterface $userAgent, LoggerInterface $logger): LoaderInterface|array;

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

    public function getSubCrawler(): Crawler
    {
        return clone $this;
    }

    public function getUserAgent(): UserAgentInterface
    {
        return $this->userAgent;
    }

    public function setUserAgent(UserAgentInterface $userAgent): static
    {
        $this->userAgent = $userAgent;

        $this->loader = $this->loader($userAgent, $this->logger);

        return $this;
    }

    public function getLogger(): LoggerInterface
    {
        return $this->logger;
    }

    /**
     * @return LoaderInterface|array<string, LoaderInterface>
     */
    public function getLoader(): LoaderInterface|array
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
     * @throws InvalidArgumentException|UnknownLoaderKeyException
     */
    public function addStep(string|StepInterface $stepOrResultKey, ?StepInterface $step = null): static
    {
        if (is_string($stepOrResultKey) && $step === null) {
            throw new InvalidArgumentException('No StepInterface object provided');
        } elseif (is_string($stepOrResultKey)) {
            $step->addToResult($stepOrResultKey);
        } else {
            $step = $stepOrResultKey;
        }

        $step->addLogger($this->logger);

        (new AddLoadersToStepAction($this->loader, $step))->invoke();

        if ($step instanceof BaseStep) {
            $step->setParentCrawler($this);
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
     * Easy way to just crawl and dump the results
     *
     * @throws Exception
     */
    public function runAndDump(): void
    {
        foreach ($this->run() as $result) {
            var_dump($result->toArray());
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
     * @throws Exception|PreRunValidationException
     */
    public function run(): Generator
    {
        if (!$this->validateSteps()) {
            return;
        }

        $inputs = $this->prepareInput();

        if ($this->firstStep()) {
            foreach ($inputs as $input) {
                $results = $this->invokeStepsRecursive($input, $this->firstStep(), 0);

                /** @var Generator<Result> $results */

                yield from $results;
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

    public function outputHook(Closure $callback): static
    {
        $this->outputHook = $callback;

        return $this;
    }

    protected function logger(): LoggerInterface
    {
        return new CliLogger();
    }

    /**
     * @return Generator<Output|Result>
     */
    protected function invokeStepsRecursive(Input $input, StepInterface $step, int $stepIndex): Generator
    {
        $outputs = $step->invokeStep($input);

        $nextStep = $this->nextStep($stepIndex);

        if (!$nextStep && $input->result === null) {
            yield from $this->storeAndReturnResults($outputs, $step->createsResult() === true, true);

            return;
        }

        foreach ($outputs as $output) {
            if ($this->monitorMemoryUsage !== false) {
                $this->logMemoryUsage();
            }

            $this->outputHook?->call($this, $output, $stepIndex, $step);

            if ($nextStep) {
                if ($input->result === null && $step->createsResult()) {
                    $childOutputs = $this->invokeStepsRecursive(
                        new Input($output),
                        $nextStep,
                        $stepIndex + 1,
                    );

                    /** @var Generator<Output> $childOutputs */

                    yield from $this->storeAndReturnResults($childOutputs, true);
                } else {
                    yield from $this->invokeStepsRecursive(
                        new Input($output),
                        $nextStep,
                        $stepIndex + 1,
                    );
                }
            } else {
                yield $output;
            }
        }
    }

    /**
     * @param Generator<Output> $outputs
     * @return Generator<Result>
     */
    protected function storeAndReturnResults(
        Generator $outputs,
        bool $manuallyDefinedResults = false,
        bool $callOutputHook = false,
    ): Generator {
        if ($manuallyDefinedResults || $this->anyResultKeysDefinedInSteps()) {
            yield from $this->storeAndReturnDefinedResults($outputs, $callOutputHook);
        } else {
            yield from $this->storeAndReturnOutputsAsResults($outputs, $callOutputHook);
        }
    }

    /**
     * @param Generator<Output> $outputs
     * @return Generator<Result>
     */
    protected function storeAndReturnDefinedResults(Generator $outputs, bool $callOutputHook = false): Generator
    {
        $results = [];

        foreach ($outputs as $output) {
            if ($callOutputHook) {
                $this->outputHook?->call($this, $output, count($this->steps) - 1, end($this->steps));
            }

            if ($output->result !== null && !in_array($output->result, $results, true)) {
                $results[] = $output->result;
            } elseif ($output->addLaterToResult !== null && !in_array($output->addLaterToResult, $results, true)) {
                $results[] = new Result($output->addLaterToResult);
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
    protected function storeAndReturnOutputsAsResults(Generator $outputs, bool $callOutputHook = false): Generator
    {
        foreach ($outputs as $output) {
            if ($callOutputHook) {
                $this->outputHook?->call($this, $output, count($this->steps) - 1, end($this->steps));
            }

            $result = new Result();

            foreach ($output->keep as $key => $value) {
                $result->set($key, $value);
            }

            if (!$this->lastStep()?->keepsAnything()) {
                if ($output->isArrayWithStringKeys()) {
                    foreach ($output->get() as $key => $value) {
                        $result->set($key, $value);
                    }
                } else {
                    $result->set('unnamed', $output->get());
                }
            }

            $this->store?->store($result);

            yield $result;
        }
    }

    /**
     * @throws PreRunValidationException
     */
    protected function validateSteps(): bool
    {
        $previousStep = null;

        foreach ($this->steps as $index => $step) {
            if ($index > 0) {
                $previousStep = $this->steps[$index - 1];
            }

            if (method_exists($step, 'validateBeforeRun')) {
                try {
                    $step->validateBeforeRun($previousStep ?? $this->inputs);
                } catch (PreRunValidationException $exception) {
                    $this->logger->error(
                        'Pre-Run validation error in step number ' . ($index + 1) . ': ' . $exception->getMessage()
                    );

                    return false;
                }
            }
        }

        return true;
    }

    /**
     * @return Input[]
     * @throws Exception
     */
    protected function prepareInput(): array
    {
        return array_map(function ($input) {
            return new Input($input);
        }, $this->inputs);
    }

    protected function anyResultKeysDefinedInSteps(): bool
    {
        foreach ($this->steps as $step) {
            if ($step->addsToOrCreatesResult()) {
                return true;
            }
        }

        return false;
    }

    protected function logMemoryUsage(): void
    {
        $memoryUsage = memory_get_usage();

        if (!is_int($this->monitorMemoryUsage) || $memoryUsage > $this->monitorMemoryUsage) {
            $this->logger->info('memory usage: ' . $memoryUsage);
        }
    }

    protected function firstStep(): ?StepInterface
    {
        return $this->steps[0] ?? null;
    }

    protected function lastStep(): ?Step
    {
        $lastStep = end($this->steps);

        if (!$lastStep instanceof Step) {
            return null;
        }

        return $lastStep;
    }

    protected function nextStep(int $afterIndex): ?StepInterface
    {
        return $this->steps[$afterIndex + 1] ?? null;
    }

    protected function reset(): void
    {
        $this->inputs = [];

        foreach ($this->steps as $step) {
            $step->resetAfterRun();
        }
    }
}
