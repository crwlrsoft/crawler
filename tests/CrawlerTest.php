<?php

namespace tests;

use Crwlr\Crawler\Crawler;
use Crwlr\Crawler\Input;
use Crwlr\Crawler\Loader\PoliteHttpLoader;
use Crwlr\Crawler\Logger\CliLogger;
use Crwlr\Crawler\Output;
use Crwlr\Crawler\Result;
use Crwlr\Crawler\Steps\GroupInterface;
use Crwlr\Crawler\Steps\Loading\LoadingStepInterface;
use Crwlr\Crawler\Steps\Step;
use Crwlr\Crawler\Steps\StepInterface;
use Crwlr\Crawler\UserAgent;
use Generator;
use Mockery;

function helper_getDummyCrawler(): Crawler
{
    $crawler = new Crawler();
    $userAgent = new UserAgent('FooBot');
    $crawler->setUserAgent($userAgent);
    $crawler->setLoader(new PoliteHttpLoader($userAgent));
    $crawler->setLogger(new CliLogger());

    return $crawler;
}

/**
 * @param mixed[] $array
 * @return Generator<mixed>
 */
function helper_getGenerator(array $array): Generator
{
    foreach ($array as $element) {
        yield $element;
    }
}

test('You can set a UserAgent', function () {
    $userAgent = new UserAgent('FooBot');
    $crawler = new Crawler();
    $crawler->setUserAgent($userAgent);

    expect($crawler->userAgent())->toBe($userAgent);
});

test('You can set a Loader', function () {
    $loader = new PoliteHttpLoader(new UserAgent('FooBot'));
    $crawler = new Crawler();
    $crawler->setLoader($loader);

    expect($crawler->loader())->toBe($loader);
});

test('You can set a Logger', function () {
    $logger = new CliLogger();
    $crawler = new Crawler();
    $crawler->setLogger($logger);

    expect($crawler->logger())->toBe($logger);
});

test('You can add steps and the Crawler class passes on its Logger and also its Loader if needed', function () {
    $step = Mockery::mock(StepInterface::class);
    $step->shouldReceive('addLogger')->once();
    $crawler = helper_getDummyCrawler();
    $crawler->setLoader(Mockery::mock(PoliteHttpLoader::class));
    $crawler->addStep($step);

    $step = Mockery::mock(LoadingStepInterface::class);
    $step->shouldReceive('addLogger')->once();
    $step->shouldReceive('addLoader')->once();
    $crawler->addStep($step);
});

test('You can add steps and they are invoked when the Crawler is run', function () {
    $step = Mockery::mock(StepInterface::class);
    $step->shouldReceive('invokeStep')->once()->andReturn(helper_getGenerator([new Output('👍🏻')]));
    $step->shouldReceive('addLogger')->once();
    $step->shouldReceive('resultDefined')->once()->andReturn(false);
    $crawler = helper_getDummyCrawler();
    $crawler->setLoader(Mockery::mock(PoliteHttpLoader::class));
    $crawler->addStep($step);

    $results = $crawler->run('randomInput');
    $results->current();
});

test('You can add step groups and the Crawler class passes on its Logger and Loader', function () {
    $group = Mockery::mock(GroupInterface::class);
    $group->shouldReceive('addLogger')->once();
    $group->shouldReceive('addLoader')->once();
    $crawler = helper_getDummyCrawler();
    $crawler->setLoader(Mockery::mock(PoliteHttpLoader::class));
    $crawler->addGroup($group);
});

test('You can add a parallel step group and it is invoked when the Crawler is run', function () {
    $group = Mockery::mock(GroupInterface::class);
    $group->shouldReceive('invokeStep')->once()->andReturn(helper_getGenerator([new Output('👍🏻')]));
    $group->shouldReceive('addLogger')->once();
    $group->shouldReceive('addLoader')->once();
    $group->shouldReceive('resultDefined')->once()->andReturn(false);
    $crawler = helper_getDummyCrawler();
    $crawler->setLoader(Mockery::mock(PoliteHttpLoader::class));
    $crawler->addGroup($group);

    $results = $crawler->run('randomInput');
    $results->current();
});

test('Result objects are created when defined and passed on through all the steps', function () {
    $crawler = helper_getDummyCrawler();
    $crawler->setLoader(Mockery::mock(PoliteHttpLoader::class));

    $step = new class () extends Step {
        /**
         * @return Generator<string>
         */
        protected function invoke(Input $input): Generator
        {
            yield 'yo';
        }
    };

    $crawler->addStep($step->initResultResource('someResource')->resultResourceProperty('prop1'));

    $step2 = new class () extends Step {
        /**
         * @return Generator<string>
         */
        protected function invoke(Input $input): Generator
        {
            yield 'lo';
        }
    };

    $crawler->addStep($step2->resultResourceProperty('prop2'));

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

    $results = $crawler->run('randomInput');
    $results = helper_generatorToArray($results);

    expect($results[0])->toBeInstanceOf(Result::class);
    expect($results[0]->name())->toBe('someResource');
    expect($results[0]->toArray())->toBe([
        'prop1' => 'yo',
        'prop2' => 'lo',
    ]);
});

test('When final steps return an array you get all values in the defined Result resource', function () {
    $crawler = helper_getDummyCrawler();
    $crawler->setLoader(Mockery::mock(PoliteHttpLoader::class));

    $step1 = new class () extends Step {
        /**
         * @return Generator<string>
         */
        protected function invoke(Input $input): Generator
        {
            yield 'Donald';
        }
    };
    $crawler->addStep($step1->initResultResource('Ducks')->resultResourceProperty('parent'));

    $step2 = new class () extends Step {
        /**
         * @return Generator<array<string>>
         */
        protected function invoke(Input $input): Generator
        {
            yield ['Tick', 'Trick', 'Track'];
        }
    };
    $crawler->addStep($step2->resultResourceProperty('children'));

    $results = $crawler->run('randomInput');

    expect($results->current()->toArray())->toBe([
        'parent' => 'Donald',
        'children' => ['Tick', 'Trick', 'Track'],
    ]);
    $results->next();
    expect($results->current())->toBeNull();
});
