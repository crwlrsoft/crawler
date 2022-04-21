<?php

namespace Crwlr\Crawler;

use AppendIterator;
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
use NoRewindIterator;
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
     * It's a generator, so when using this method directly, you need to traverse the generator, otherwise nothing
     * happens. Alternatively you can use runAndTraverse().
     *
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

                $nextIterationInputs->append(new NoRewindIterator($step->invokeStep($input)));
            }

            if ($step->outputsShallBeUnique()) {
                $nextIterationInputs = $this->filterDuplicateOutputs($nextIterationInputs);
            }

            if ($step !== end($this->steps)) {
                $inputs = $nextIterationInputs;
            } else {
                $outputs = $nextIterationInputs;
            }
        }

        if (isset($outputs)) {
            yield from $this->storeAndReturnResults($outputs);
        }

        $this->inputs = [];
    }

    protected function logger(): LoggerInterface
    {
        return new CliLogger();
    }

    /**
     * @param AppendIterator<Output>|Generator<Output> $outputs
     * @return Generator<Result>
     */
    private function storeAndReturnResults(AppendIterator|Generator $outputs): Generator
    {
        if ($this->anyResultKeysDefinedInSteps()) {
            yield from $this->storeAndReturnDefinedResults($outputs);
        } else {
            yield from $this->storeAndReturnOutputsAsResults($outputs);
        }
    }

    /**
     * @param AppendIterator<Output>|Generator<Output> $outputs
     * @return Generator<Result>
     */
    private function storeAndReturnDefinedResults(AppendIterator|Generator $outputs): Generator
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
     * @param AppendIterator<Output>|Generator<Output> $outputs
     * @return Generator<Result>
     */
    private function storeAndReturnOutputsAsResults(AppendIterator|Generator $outputs): Generator
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

    private function filterDuplicateOutputs(AppendIterator $outputs): Generator
    {
        $uniqueKeys = [];

        foreach ($outputs as $output) {
            if (isset($uniqueKeys[$output->getKey()])) {
                continue;
            }

            $uniqueKeys[$output->getKey()] = true;

            yield $output;
        }
    }
}
