<?php

namespace tests;

use Crwlr\Crawler\Crawler;
use Crwlr\Crawler\Input;
use Crwlr\Crawler\Loader\LoaderInterface;
use Crwlr\Crawler\Loader\PoliteHttpLoader;
use Crwlr\Crawler\Logger\CliLogger;
use Crwlr\Crawler\Output;
use Crwlr\Crawler\Result;
use Crwlr\Crawler\Steps\Loading\Http;
use Crwlr\Crawler\Steps\Loading\LoadingStepInterface;
use Crwlr\Crawler\Steps\Step;
use Crwlr\Crawler\Steps\StepInterface;
use Crwlr\Crawler\UserAgents\BotUserAgent;
use Crwlr\Crawler\UserAgents\UserAgentInterface;
use Generator;
use Mockery;
use Psr\Log\LoggerInterface;

function helper_getDummyCrawler(): Crawler
{
    return new class () extends Crawler {
        public function userAgent(): UserAgentInterface
        {
            return new BotUserAgent('FooBot');
        }

        public function loader(UserAgentInterface $userAgent, LoggerInterface $logger): LoaderInterface
        {
            return Mockery::mock(LoaderInterface::class);
        }
    };
}

function helper_getDummyStepYieldingInput(): Step
{
    return new class () extends Step {
        protected function invoke(Input $input): Generator
        {
            yield $input->get();
        }
    };
}

function helper_getDummyCrawlerWithDummyStepYieldingInput(): Crawler
{
    $crawler = helper_getDummyCrawler();

    $step = helper_getDummyStepYieldingInput();

    $crawler->addStep($step);

    return $crawler;
}

test(
    'The methods to define UserAgent, Logger and Loader instances are called in construct and the getter methods ' .
    'always return the same instance.',
    function () {
        $crawler = new class () extends Crawler {
            public int $userAgentCalled = 0;
            public int $loggerCalled = 0;
            public int $loaderCalled = 0;

            protected function userAgent(): UserAgentInterface
            {
                $this->userAgentCalled += 1;

                return new class ('FooBot') extends BotUserAgent {
                    public string $testProperty = 'foo';
                };
            }

            protected function logger(): LoggerInterface
            {
                $this->loggerCalled += 1;

                return new class () extends CliLogger {
                    public string $testProperty = 'foo';
                };
            }

            protected function loader(UserAgentInterface $userAgent, LoggerInterface $logger): LoaderInterface
            {
                $this->loaderCalled += 1;

                return new class ($userAgent, null, $logger) extends PoliteHttpLoader {
                    public string $testProperty = 'foo';
                };
            }
        };
        expect($crawler->getUserAgent()->testProperty)->toBe('foo'); // @phpstan-ignore-line
        expect($crawler->getLogger()->testProperty)->toBe('foo');  // @phpstan-ignore-line
        expect($crawler->getLoader()->testProperty)->toBe('foo');  // @phpstan-ignore-line
        expect($crawler->userAgentCalled)->toBe(1);
        expect($crawler->loggerCalled)->toBe(1);
        expect($crawler->loaderCalled)->toBe(1);

        $crawler->getUserAgent()->testProperty = 'bar'; // @phpstan-ignore-line
        $crawler->getLogger()->testProperty = 'bar'; // @phpstan-ignore-line
        $crawler->getLoader()->testProperty = 'bar'; // @phpstan-ignore-line
        $crawler->addStep(Http::get()); // adding steps passes on logger and loader, should use the same instances

        expect($crawler->getUserAgent()->testProperty)->toBe('bar'); // @phpstan-ignore-line
        expect($crawler->getLogger()->testProperty)->toBe('bar');  // @phpstan-ignore-line
        expect($crawler->getLoader()->testProperty)->toBe('bar');  // @phpstan-ignore-line
        expect($crawler->userAgentCalled)->toBe(1);
        expect($crawler->loggerCalled)->toBe(1);
        expect($crawler->loaderCalled)->toBe(1);
    }
);

test('You can set a single input for the first step using the input method', function () {
    $crawler = helper_getDummyCrawlerWithDummyStepYieldingInput();

    $crawler->input('https://www.example.com');

    $results = helper_generatorToArray($crawler->run());

    expect($results[0]->toArray()['unnamed'])->toBe('https://www.example.com');
});

test('You can set multiple inputs by multiply calling the input method', function () {
    $crawler = helper_getDummyCrawlerWithDummyStepYieldingInput();

    $crawler->input('https://www.crwl.io');

    $crawler->input('https://www.otsch.codes');

    $results = helper_generatorToArray($crawler->run());

    expect($results[0]->toArray()['unnamed'])->toBe('https://www.crwl.io');

    expect($results[1]->toArray()['unnamed'])->toBe('https://www.otsch.codes');
});

test('You can set multiple inputs using the inputs (plural) method', function () {
    $crawler = helper_getDummyCrawlerWithDummyStepYieldingInput();

    $crawler->inputs(['https://www.crwl.io', 'https://www.otsch.codes']);

    $results = helper_generatorToArray($crawler->run());

    expect($results[0]->toArray()['unnamed'])->toBe('https://www.crwl.io');

    expect($results[1]->toArray()['unnamed'])->toBe('https://www.otsch.codes');
});

test('Initial inputs are reset after the crawler was run', function () {
    $crawler = helper_getDummyCrawlerWithDummyStepYieldingInput();

    $crawler->inputs(['https://www.crwl.io', 'https://www.otsch.codes']);

    $results = helper_generatorToArray($crawler->run());

    expect($results)->toHaveCount(2);

    $crawler->input('https://fetzi.dev/');

    $results = helper_generatorToArray($crawler->run());

    expect($results)->toHaveCount(1);
});

test('The static loop method wraps a Step in a LoopStep object', function () {
    $step = Mockery::mock(StepInterface::class);
    $step->shouldReceive('invokeStep')->withArgs(function (Input $input) {
        return $input->get() === 'foo';
    });
    $loop = Crawler::loop($step);
    $loop->invokeStep(new Input('foo'));
});

test('You can add steps and the Crawler class passes on its Logger and also its Loader if needed', function () {
    $step = Mockery::mock(StepInterface::class);
    $step->shouldReceive('addLogger')->once();
    $crawler = helper_getDummyCrawler();
    $crawler->addStep($step);

    $step = Mockery::mock(LoadingStepInterface::class);
    $step->shouldReceive('addLogger')->once();
    $step->shouldReceive('addLoader')->once();
    $crawler->addStep($step);
});

test('You can add steps and they are invoked when the Crawler is run', function () {
    $step = Mockery::mock(StepInterface::class);
    $step->shouldReceive('invokeStep')->once()->andReturn(helper_arrayToGenerator([new Output('👍🏻')]));
    $step->shouldReceive('addLogger')->once();
    $step->shouldReceive('getResultKey')->once()->andReturn(null);
    $crawler = helper_getDummyCrawler();
    $crawler->addStep($step);
    $crawler->input('randomInput');

    $results = $crawler->run();
    $results->current();
});

test('You can add a step group as a step and all it\'s steps are invoked when the Crawler is run', function () {
    $crawler = helper_getDummyCrawler();
    $step1 = Mockery::mock(StepInterface::class);
    $step1->shouldReceive('invokeStep')->andReturn(helper_arrayToGenerator(['foo']));
    $step1->shouldReceive('addLogger');
    $step2 = Mockery::mock(StepInterface::class);
    $step2->shouldReceive('invokeStep')->andReturn(helper_arrayToGenerator(['bar']));
    $step2->shouldReceive('addLogger');
    $step3 = Mockery::mock(StepInterface::class);
    $step3->shouldReceive('invokeStep')->andReturn(helper_arrayToGenerator(['baz']));
    $step3->shouldReceive('addLogger');
    $crawler->addStep(
        Crawler::group()
            ->addStep($step1)
            ->addStep($step2)
            ->addStep($step3)
    );
});

test('Result objects are created when defined and passed on through all the steps', function () {
    $crawler = helper_getDummyCrawler();

    $step = new class () extends Step {
        /**
         * @return Generator<string>
         */
        protected function invoke(Input $input): Generator
        {
            yield 'yo';
        }
    };

    $crawler->addStep($step->setResultKey('prop1'));

    $step2 = new class () extends Step {
        /**
         * @return Generator<string>
         */
        protected function invoke(Input $input): Generator
        {
            yield 'lo';
        }
    };

    $crawler->addStep($step2->setResultKey('prop2'));

    $step3 = new class () extends Step {
        /**
         * @return Generator<string>
         */
        protected function invoke(Input $input): Generator
        {
            yield 'foo';
        }
    };

    $crawler->addStep($step3);

    $step4 = new class () extends Step {
        /**
         * @return Generator<string>
         */
        protected function invoke(Input $input): Generator
        {
            yield 'bar';
        }
    };

    $crawler->addStep($step4);
    $crawler->input('randomInput');

    $results = $crawler->run();
    $results = helper_generatorToArray($results);

    expect($results[0])->toBeInstanceOf(Result::class);
    expect($results[0]->toArray())->toBe([
        'prop1' => 'yo',
        'prop2' => 'lo',
    ]);
});

test('When final steps return an array you get all values in the defined Result resource', function () {
    $crawler = helper_getDummyCrawler();

    $step1 = new class () extends Step {
        /**
         * @return Generator<string>
         */
        protected function invoke(Input $input): Generator
        {
            yield 'Donald';
        }
    };
    $crawler->addStep($step1->setResultKey('parent'));

    $step2 = new class () extends Step {
        /**
         * @return Generator<array<string>>
         */
        protected function invoke(Input $input): Generator
        {
            yield ['Tick', 'Trick', 'Track'];
        }
    };
    $crawler->addStep($step2->setResultKey('children'));
    $crawler->input('randomInput');

    $results = $crawler->run();

    expect($results->current()->toArray())->toBe([
        'parent' => 'Donald',
        'children' => ['Tick', 'Trick', 'Track'],
    ]);
    $results->next();
    expect($results->current())->toBeNull();
});
