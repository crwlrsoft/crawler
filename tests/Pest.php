<?php

namespace tests;

use Crwlr\Crawler\Input;
use Crwlr\Crawler\Output;
use Crwlr\Crawler\Steps\Step;
use Crwlr\Crawler\Steps\StepInterface;
use Generator;
use GuzzleHttp\Psr7\Response;
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
