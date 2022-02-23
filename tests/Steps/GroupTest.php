<?php

namespace tests\Steps;

use Crwlr\Crawler\Input;
use Crwlr\Crawler\Loader\HttpLoader;
use Crwlr\Crawler\Logger\CliLogger;
use Crwlr\Crawler\Output;
use Crwlr\Crawler\Steps\Group;
use Crwlr\Crawler\Steps\GroupInterface;
use Crwlr\Crawler\Steps\Loading\LoadingStepInterface;
use Crwlr\Crawler\Steps\StepInterface;
use Crwlr\Crawler\UserAgents\BotUserAgent;
use Generator;
use Mockery;

class DummyGroup extends Group
{
    public static function new(): GroupInterface
    {
        return new self();
    }

    public function invokeStep(Input $input): Generator
    {
        yield new Output('');
    }
}

test('You can add a step and it passes on the logger', function () {
    $step = Mockery::mock(StepInterface::class);
    $step->shouldReceive('addLogger')->once();
    $step->shouldNotReceive('addLoader');
    $group = new DummyGroup();
    $group->addLogger(new CliLogger());
    $group->addStep($step);
});

test('It also passes on the loader to the step when addLoader method exists in step', function () {
    $step = Mockery::mock(LoadingStepInterface::class);
    $step->shouldReceive('addLogger')->once();
    $step->shouldReceive('addLoader')->once();
    $group = new DummyGroup();
    $group->addLogger(new CliLogger());
    $group->addLoader(new HttpLoader(new BotUserAgent('MyBot')));
    $group->addStep($step);
});
