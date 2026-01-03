<?php

namespace tests;

use Crwlr\Crawler\HttpCrawler;
use Crwlr\Crawler\Input;
use Crwlr\Crawler\Loader\Http\HttpLoader;
use Crwlr\Crawler\Loader\Http\Messages\RespondedRequest;
use Crwlr\Crawler\Loader\Http\Politeness\Throttler;
use Crwlr\Crawler\Loader\Http\Politeness\TimingUnits\MultipleOf;
use Crwlr\Crawler\Loader\LoaderInterface;
use Crwlr\Crawler\Output;
use Crwlr\Crawler\Steps\Loading\LoadingStep;
use Crwlr\Crawler\Steps\Step;
use Crwlr\Crawler\Steps\StepInterface;
use Crwlr\Crawler\Steps\StepOutputType;
use Crwlr\Crawler\UserAgents\UserAgent;
use Crwlr\Crawler\UserAgents\UserAgentInterface;
use Crwlr\Crawler\Utils\OutputTypeHelper;
use Crwlr\Utils\Microseconds;
use Generator;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Utils;
use Psr\Http\Client\ClientInterface;
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
                'php -S localhost:8000 ' . __DIR__ . '/_Integration/Server.php',
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

function helper_dump(mixed $var): void
{
    error_log(var_export($var, true));
}

function helper_dieDump(mixed $var): void
{
    var_dump($var);
    ob_end_flush();
    exit;
}

function helper_getValueReturningStep(mixed $value): Step
{
    return new class ($value) extends Step {
        public function __construct(private mixed $value) {}

        protected function invoke(mixed $input): Generator
        {
            yield $this->value;
        }

        public function outputType(): StepOutputType
        {
            return OutputTypeHelper::isAssociativeArrayOrObject($this->value) ?
                StepOutputType::AssociativeArrayOrObject :
                StepOutputType::Scalar;
        }
    };
}

function helper_getInputReturningStep(): Step
{
    return new class extends Step {
        protected function invoke(mixed $input): Generator
        {
            yield $input;
        }
    };
}

function helper_getNumberIncrementingStep(): Step
{
    return new class extends Step {
        protected function invoke(mixed $input): Generator
        {
            yield $input + 1;
        }
    };
}

function helper_getStepYieldingMultipleNumbers(): Step
{
    return new class extends Step {
        protected function invoke(mixed $input): Generator
        {
            foreach (['one', 'two', 'two', 'three', 'four', 'three', 'five', 'three'] as $number) {
                yield $number;
            }
        }
    };
}

function helper_getStepYieldingMultipleArraysWithNumber(): Step
{
    return new class extends Step {
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
        public function __construct(private int $number) {}

        protected function invoke(mixed $input): Generator
        {
            yield helper_getStdClassWithData(
                ['number' => $this->number, 'foo' => 'bar' . (is_int($input) ? ' ' . $input : '')],
            );
        }
    };
}

function helper_getStepYieldingMultipleObjectsWithNumber(): Step
{
    return new class extends Step {
        protected function invoke(mixed $input): Generator
        {
            foreach (['one', 'two', 'two', 'three', 'four', 'three', 'five', 'three'] as $key => $number) {
                yield helper_getStdClassWithData(
                    ['number' => $number, 'foo' => 'bar' . ($input === true ? ' ' . $key : '')],
                );
            }
        }
    };
}

function helper_getStepYieldingInputArrayAsSeparateOutputs(): Step
{
    return new class extends Step {
        protected function invoke(mixed $input): Generator
        {
            foreach ($input as $output) {
                yield $output;
            }
        }
    };
}

function helper_getLoadingStep(): Step
{
    return new class extends Step {
        /**
         * @use LoadingStep<LoaderInterface>
         */
        use LoadingStep;

        protected function invoke(mixed $input): Generator
        {
            yield 'yo';
        }
    };
}

function helper_getDummyRobotsTxtResponse(?string $forDomain = null): Response
{
    return new Response(
        200,
        [],
        "User-agent: FooBot\n" .
        "Disallow: " . ($forDomain ? '/' . $forDomain . '/secret' : 'secret'),
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

function helper_getFastLoader(
    ?UserAgentInterface $userAgent = null,
    ?LoggerInterface $logger = null,
    ?ClientInterface $httpClient = null,
): HttpLoader {
    $loader = new HttpLoader($userAgent ?? UserAgent::mozilla5CompatibleBrowser(), $httpClient, $logger);

    $loader->throttle()
        ->waitBetween(new MultipleOf(0.0001), new MultipleOf(0.0002))
        ->waitAtLeast(Microseconds::fromSeconds(0.0001));

    return $loader;
}

function helper_getFastCrawler(): HttpCrawler
{
    return new class extends HttpCrawler {
        protected function userAgent(): UserAgentInterface
        {
            return new UserAgent('TestBot');
        }

        protected function loader(UserAgentInterface $userAgent, LoggerInterface $logger): LoaderInterface
        {
            return helper_getFastLoader($userAgent, $logger);
        }
    };
}

function helper_nonBotUserAgent(): UserAgent
{
    return new UserAgent('Mozilla/5.0 (compatible; FooBot)');
}

function helper_getMinThrottler(): Throttler
{
    return new Throttler(new MultipleOf(0.0001), new MultipleOf(0.0002), Microseconds::fromSeconds(0.0001));
}

/**
 * @param array<string, string|string[]> $requestHeaders
 * @param array<string, string|string[]> $responseHeaders
 */
function helper_getRespondedRequest(
    string $method = 'GET',
    string $url = 'https://www.example.com/foo',
    array $requestHeaders = [],
    ?string $requestBody = null,
    int $statusCode = 200,
    array $responseHeaders = [],
    ?string $responseBody = null,
): RespondedRequest {
    if ($requestBody !== null) {
        $request = new Request($method, $url, $requestHeaders, Utils::streamFor($requestBody));
    } else {
        $request = new Request($method, $url, $requestHeaders);
    }

    if ($responseBody !== null) {
        $response = new Response($statusCode, $responseHeaders, body: Utils::streamFor($responseBody));
    } else {
        $response = new Response($statusCode, $responseHeaders);
    }

    return new RespondedRequest($request, $response);
}

function helper_cachedir(?string $inDir = null): string
{
    $path = __DIR__ . '/_Temp/_cachedir';

    if ($inDir !== null) {
        return $path . (str_starts_with($inDir, '/') ? $inDir : '/' . $inDir);
    }

    return $path;
}

function helper_resetCacheDir(): void
{
    helper_resetTempDir(helper_cachedir());
}

function helper_storagedir(?string $inDir = null): string
{
    $path = __DIR__ . '/_Temp/_storagedir';

    if ($inDir !== null) {
        return $path . (str_starts_with($inDir, '/') ? $inDir : '/' . $inDir);
    }

    return $path;
}

function helper_resetStorageDir(): void
{
    helper_resetTempDir(helper_storagedir());
}

function helper_resetTempDir(string $dirPath): void
{
    $files = scandir($dirPath);

    if (is_array($files)) {
        foreach ($files as $file) {
            if ($file === '.' || $file === '..' || $file === '.gitkeep') {
                continue;
            }

            @unlink($dirPath . '/' . $file);
        }
    }
}

function helper_testfilesdir(?string $inDir = null): string
{
    $path = __DIR__ . '/_Temp/_testfilesdir';

    if ($inDir !== null) {
        return $path . (str_starts_with($inDir, '/') ? $inDir : '/' . $inDir);
    }

    return $path;
}
