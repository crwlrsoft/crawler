<?php

namespace tests;

use Crwlr\Crawler\Crawler;
use Crwlr\Crawler\Input;
use Crwlr\Crawler\Loader\LoaderInterface;
use Crwlr\Crawler\Loader\Http\PoliteHttpLoader;
use Crwlr\Crawler\Logger\CliLogger;
use Crwlr\Crawler\Output;
use Crwlr\Crawler\Result;
use Crwlr\Crawler\Steps\Loading\Http;
use Crwlr\Crawler\Steps\Loading\LoadingStepInterface;
use Crwlr\Crawler\Steps\Step;
use Crwlr\Crawler\Steps\StepInterface;
use Crwlr\Crawler\Stores\StoreInterface;
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

function helper_getDummyCrawlerWithInputReturningStep(): Crawler
{
    $crawler = helper_getDummyCrawler();

    $step = helper_getInputReturningStep();

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

it('gives you the current memory limit', function () {
    expect(Crawler::getMemoryLimit())->toBeString();
});

it('changes the current memory limit when allowed', function () {
    $currentLimit = Crawler::getMemoryLimit();

    if ($currentLimit === '512M') {
        $newValue = '1G';
    } else {
        $newValue = '512M';
    }

    $setLimitReturnValue = Crawler::setMemoryLimit($newValue);

    if ($setLimitReturnValue === false) {
        expect(Crawler::getMemoryLimit())->toBe($currentLimit);
    } else {
        expect(Crawler::getMemoryLimit())->toBe($newValue);
    }
});

test('You can set a single input for the first step using the input method', function () {
    $crawler = helper_getDummyCrawlerWithInputReturningStep();

    $crawler->input('https://www.example.com');

    $results = helper_generatorToArray($crawler->run());

    expect($results[0]->toArray()['unnamed'])->toBe('https://www.example.com');
});

test('You can set multiple inputs by multiply calling the input method', function () {
    $crawler = helper_getDummyCrawlerWithInputReturningStep();

    $crawler->input('https://www.crwl.io');

    $crawler->input('https://www.otsch.codes');

    $results = helper_generatorToArray($crawler->run());

    expect($results[0]->toArray()['unnamed'])->toBe('https://www.crwl.io');

    expect($results[1]->toArray()['unnamed'])->toBe('https://www.otsch.codes');
});

test('You can set multiple inputs using the inputs (plural) method', function () {
    $crawler = helper_getDummyCrawlerWithInputReturningStep();

    $crawler->inputs(['https://www.crwl.io', 'https://www.otsch.codes']);

    $results = helper_generatorToArray($crawler->run());

    expect($results[0]->toArray()['unnamed'])->toBe('https://www.crwl.io');

    expect($results[1]->toArray()['unnamed'])->toBe('https://www.otsch.codes');
});

test('Initial inputs are reset after the crawler was run', function () {
    $crawler = helper_getDummyCrawlerWithInputReturningStep();

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

    $step->shouldReceive('addLogger', 'outputsShallBeUnique')->once();

    $step->shouldReceive('addsToOrCreatesResult')->once()->andReturn(false);

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

    $step1->shouldReceive('getResultKey');

    $step2 = Mockery::mock(StepInterface::class);

    $step2->shouldReceive('invokeStep')->andReturn(helper_arrayToGenerator(['bar']));

    $step2->shouldReceive('addLogger');

    $step2->shouldReceive('getResultKey');

    $step3 = Mockery::mock(StepInterface::class);

    $step3->shouldReceive('invokeStep')->andReturn(helper_arrayToGenerator(['baz']));

    $step3->shouldReceive('addLogger');

    $step3->shouldReceive('getResultKey');

    $crawler->addStep(
        Crawler::group()
            ->addStep($step1)
            ->addStep($step2)
            ->addStep($step3)
    );
});

test('Result objects are created when defined and passed on through all the steps', function () {
    $crawler = helper_getDummyCrawler();

    $step = helper_getValueReturningStep('yo');

    $crawler->addStep($step->setResultKey('prop1'));

    $step2 = helper_getValueReturningStep('lo');

    $crawler->addStep($step2->setResultKey('prop2'));

    $step3 = helper_getValueReturningStep('foo');

    $crawler->addStep($step3);

    $step4 = helper_getValueReturningStep('bar');

    $crawler->addStep($step4);

    $crawler->input('randomInput');

    $results = helper_generatorToArray($crawler->run());

    expect($results[0])->toBeInstanceOf(Result::class);

    expect($results[0]->toArray())->toBe([
        'prop1' => 'yo',
        'prop2' => 'lo',
    ]);
});

it('doesn\'t pass on outputs of one step to the next one when dontCascade was called', function () {
    $crawler = helper_getDummyCrawler();

    $step1 = helper_getInputReturningStep();

    $step1->dontCascade();

    $step2 = Mockery::mock(StepInterface::class);

    $step2->shouldNotReceive('invokeStep');

    $crawler->runAndTraverse();
});

test('When final steps return an array you get all values in the defined Result resource', function () {
    $crawler = helper_getDummyCrawler();

    $step1 = helper_getValueReturningStep('Donald');

    $crawler->addStep($step1->setResultKey('parent'));

    $step2 = helper_getValueReturningStep(['Tick', 'Trick', 'Track']);

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

it('sends all results to the Store when there is one and still yields the results', function () {
    $store = Mockery::mock(StoreInterface::class);

    $store->shouldReceive('store')->times(3);

    $crawler = helper_getDummyCrawler();

    $crawler->input('gogogo');

    $crawler->setStore($store);

    $step = new class () extends Step {
        protected function invoke(mixed $input): Generator
        {
            yield 'one';
            yield 'two';
            yield 'three';
        }
    };

    $crawler->addStep('number', $step);

    $results = helper_generatorToArray($crawler->run());

    expect($results)->toHaveCount(3);
});

it(
    'actually runs the crawler without the need to traverse results manually, when runAndTraverse is called',
    function () {
        $step1 = Mockery::mock(StepInterface::class);

        $step1->shouldNotReceive('invokeStep');

        $step1->shouldReceive('addLogger');

        $crawler = helper_getDummyCrawler()
            ->addStep($step1)
            ->input('test');

        $crawler->run();

        $step2 = Mockery::mock(StepInterface::class);

        $step2->shouldReceive('invokeStep')->andYield(new Output('yo'));

        $step2->shouldReceive('addLogger', 'outputsShallBeUnique', 'addsToOrCreatesResult');

        $crawler = helper_getDummyCrawler()
            ->addStep($step2)
            ->input('test');

        $crawler->runAndTraverse();
    }
);

it('yields only unique outputs from a step when uniqueOutput was called', function () {
    $crawler = helper_getDummyCrawler();

    $crawler->addStep(helper_getInputReturningStep()->uniqueOutputs());

    $crawler->inputs(['one', 'two', 'three', 'one', 'three', 'four', 'one', 'five', 'two']);

    $results = helper_generatorToArray($crawler->run());

    expect($results)->toHaveCount(5);
});
