<?php

namespace tests;

use Crwlr\Crawler\HttpCrawler;
use Crwlr\Crawler\Input;
use Crwlr\Crawler\Loader\Http\HttpLoader;
use Crwlr\Crawler\Loader\Http\Politeness\TimingUnits\Microseconds;
use Crwlr\Crawler\Loader\Http\Politeness\TimingUnits\MultipleOf;
use Crwlr\Crawler\Loader\LoaderInterface;
use Crwlr\Crawler\Output;
use Crwlr\Crawler\Steps\Step;
use Crwlr\Crawler\Steps\StepInterface;
use Crwlr\Crawler\UserAgents\UserAgent;
use Crwlr\Crawler\UserAgents\UserAgentInterface;
use Generator;
use GuzzleHttp\Psr7\Response;
use Psr\Log\LoggerInterface;
use stdClass;
use Symfony\Component\Process\Process;

class TestServerProcess
{
    public static ?Process $process = null;
}

uses()
    ->group('integration')
    ->beforeEach(function () {
        if (!isset(TestServerProcess::$process)) {
            TestServerProcess::$process = Process::fromShellCommandline(
                'php -S localhost:8000 ' . __DIR__ . '/_Integration/Server.php'
            );

            TestServerProcess::$process->start();

            usleep(100000);
        }
    })
    ->afterAll(function () {
        TestServerProcess::$process?->stop(3, SIGINT);

        TestServerProcess::$process = null;
    })
    ->in('_Integration');

function helper_getValueReturningStep(mixed $value): Step
{
    return new class ($value) extends Step {
        public function __construct(private mixed $value)
        {
        }

        protected function invoke(mixed $input): Generator
        {
            yield $this->value;
        }
    };
}

function helper_getInputReturningStep(): Step
{
    return new class () extends Step {
        protected function invoke(mixed $input): Generator
        {
            yield $input;
        }
    };
}

function helper_getNumberIncrementingStep(): Step
{
    return new class () extends Step {
        protected function invoke(mixed $input): Generator
        {
            yield $input + 1;
        }
    };
}

function helper_getStepYieldingMultipleNumbers(): Step
{
    return new class () extends Step {
        protected function invoke(mixed $input): Generator
        {
            foreach (['one', 'two', 'two', 'three', 'four', 'three', 'five', 'three'] as $number) {
                yield $number;
            }
        }
    };
}

function helper_getStepYieldingArrayWithNumber(int $number): Step
{
    return new class ($number) extends Step {
        public function __construct(private int $number)
        {
        }

        protected function invoke(mixed $input): Generator
        {
            yield ['number' => $this->number, 'foo' => 'bar' . (is_int($input) ? ' ' . $input : '')];
        }
    };
}

function helper_getStepYieldingMultipleArraysWithNumber(): Step
{
    return new class () extends Step {
        protected function invoke(mixed $input): Generator
        {
            foreach (['one', 'two', 'two', 'three', 'four', 'three', 'five', 'three'] as $key => $number) {
                yield ['number' => $number, 'foo' => 'bar' . ($input === true ? ' ' . $key : '')];
            }
        }
    };
}

function helper_getStepYieldingObjectWithNumber(int $number): Step
{
    return new class ($number) extends Step {
        public function __construct(private int $number)
        {
        }

        protected function invoke(mixed $input): Generator
        {
            yield helper_getStdClassWithData(
                ['number' => $this->number, 'foo' => 'bar' . (is_int($input) ? ' ' . $input : '')]
            );
        }
    };
}

function helper_getStepYieldingMultipleObjectsWithNumber(): Step
{
    return new class () extends Step {
        protected function invoke(mixed $input): Generator
        {
            foreach (['one', 'two', 'two', 'three', 'four', 'three', 'five', 'three'] as $key => $number) {
                yield helper_getStdClassWithData(
                    ['number' => $number, 'foo' => 'bar' . ($input === true ? ' ' . $key : '')]
                );
            }
        }
    };
}

function helper_getDummyRobotsTxtResponse(?string $forDomain = null): Response
{
    return new Response(
        200,
        [],
        "User-agent: FooBot\n" .
        "Disallow: " . ($forDomain ? '/' . $forDomain . '/secret' : 'secret')
    );
}

/**
 * @param iterable<mixed> $iterable
 * @return void
 */
function helper_traverseIterable(iterable $iterable): void
{
    foreach ($iterable as $key => $value) {
        // just traverse
    }
}

/**
 * @param mixed[] $array
 * @return Generator<mixed>
 */
function helper_arrayToGenerator(array $array): Generator
{
    foreach ($array as $element) {
        yield $element;
    }
}

/**
 * @param Generator<mixed> $generator
 * @return mixed[]
 */
function helper_generatorToArray(Generator $generator): array
{
    $array = [];

    foreach ($generator as $value) {
        $array[] = $value;
    }

    return $array;
}

/**
 * @return Output[]
 */
function helper_invokeStepWithInput(StepInterface $step, mixed $input = null): array
{
    return helper_generatorToArray($step->invokeStep(new Input($input ?? 'anything')));
}

function helper_getStepFilesContent(string $filePathInFilesFolder): string
{
    $content = file_get_contents(__DIR__ . '/Steps/_Files/' . $filePathInFilesFolder);

    if ($content === false) {
        return '';
    }

    return $content;
}

/**
 * @param mixed[] $data
 */
function helper_getStdClassWithData(array $data): stdClass
{
    $object = new stdClass();

    foreach ($data as $key => $value) {
        $object->{$key} = $value;
    }

    return $object;
}

function helper_getSimpleListHtml(): string
{
    return <<<HTML
        <ul id="list">
            <li class="item">one</li>
            <li class="item">two</li>
            <li class="item">three</li>
            <li class="item">four</li>
        </ul>
        HTML;
}

function helper_getFastLoader(UserAgentInterface $userAgent, ?LoggerInterface $logger = null): HttpLoader
{
    $loader = new HttpLoader($userAgent, logger: $logger);

    $loader->throttle()
        ->waitBetween(new MultipleOf(0.0001), new MultipleOf(0.0002))
        ->waitAtLeast(Microseconds::fromSeconds(0.0001));

    return $loader;
}

function helper_getFastCrawler(): HttpCrawler
{
    return new class () extends HttpCrawler {
        protected function userAgent(): UserAgentInterface
        {
            return new UserAgent('TestBot');
        }

        protected function loader(UserAgentInterface $userAgent, LoggerInterface $logger): LoaderInterface|array
        {
            return helper_getFastLoader($userAgent, $logger);
        }
    };
}
