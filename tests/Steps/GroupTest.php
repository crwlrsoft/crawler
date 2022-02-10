<?php

namespace tests\Steps;

use Crwlr\Crawler\Input;
use Crwlr\Crawler\Loader\HttpLoader;
use Crwlr\Crawler\Logger\CliLogger;
use Crwlr\Crawler\Output;
use Crwlr\Crawler\Steps\Group;
use Crwlr\Crawler\Steps\Loading\LoadingStepInterface;
use Crwlr\Crawler\Steps\StepInterface;
use Crwlr\Crawler\UserAgent;
use Mockery;

test('The factory method returns a Group object instance', function () {
    expect(Group::new())->toBeInstanceOf(Group::class);
});

test('You can add a step and it passes on the logger', function() {
    $step = Mockery::mock(StepInterface::class);
    $step->shouldReceive('addLogger')->once();
    $step->shouldNotReceive('addLoader');
    $group = new Group();
    $group->addLogger(new CliLogger());
    $group->addStep($step);
});

test('It also passes on the loader to the step when addLoader method exists in step', function () {
    $step = Mockery::mock(LoadingStepInterface::class);
    $step->shouldReceive('addLogger')->once();
    $step->shouldReceive('addLoader')->once();
    $group = new Group();
    $group->addLogger(new CliLogger());
    $group->addLoader(new HttpLoader(new UserAgent('MyBot')));
    $group->addStep($step);
});

test('You can add multiple steps and invokeStep calls all of them', function () {
    $step1 = Mockery::mock(StepInterface::class);
    $step1->shouldReceive('addLogger')->once();
    $step1->shouldReceive('invokeStep')->once();
    $step2 = Mockery::mock(StepInterface::class);
    $step2->shouldReceive('addLogger')->once();
    $step2->shouldReceive('invokeStep')->once();
    $step3 = Mockery::mock(StepInterface::class);
    $step3->shouldReceive('addLogger')->once();
    $step3->shouldReceive('invokeStep')->once();

    $group = new Group();
    $group->addLogger(new CliLogger());
    $group->addStep($step1)->addStep($step2)->addStep($step3);
    $group->invokeStep(new Input('foo'));
});

test('It returns the results of all steps when invoked', function () {
    $step1 = Mockery::mock(StepInterface::class);
    $step1->shouldReceive('addLogger')->once();
    $step1->shouldReceive('invokeStep')->once()->andReturn([new Output('1')]);
    $step2 = Mockery::mock(StepInterface::class);
    $step2->shouldReceive('addLogger')->once();
    $step2->shouldReceive('invokeStep')->once()->andReturn([new Output('2')]);
    $step3 = Mockery::mock(StepInterface::class);
    $step3->shouldReceive('addLogger')->once();
    $step3->shouldReceive('invokeStep')->once()->andReturn([new Output('3')]);;

    $group = new Group();
    $group->addLogger(new CliLogger());
    $group->addStep($step1)->addStep($step2)->addStep($step3);
    $result = $group->invokeStep(new Input('foo'));

    expect($result)->toBeArray();
    expect($result)->toHaveCount(3);
    expect($result[0])->toBeInstanceOf(Output::class);
    expect($result[0]->get())->toBe('1');
    expect($result[1])->toBeInstanceOf(Output::class);
    expect($result[1]->get())->toBe('2');
    expect($result[2])->toBeInstanceOf(Output::class);
    expect($result[2]->get())->toBe('3');
});
