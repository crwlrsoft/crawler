<?php

namespace tests\Steps;

use Crwlr\Crawler\Input;
use Crwlr\Crawler\Logger\CliLogger;
use Crwlr\Crawler\Output;
use Crwlr\Crawler\Steps\ParallelGroup;
use Crwlr\Crawler\Steps\StepInterface;
use Mockery;
use function tests\helper_arrayToGenerator;
use function tests\helper_generatorToArray;
use function tests\helper_traverseIterable;

test('The factory method returns a Group object instance', function () {
    expect(ParallelGroup::new())->toBeInstanceOf(ParallelGroup::class);
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

    $group = new ParallelGroup();
    $group->addLogger(new CliLogger());
    $group->addStep($step1)->addStep($step2)->addStep($step3);
    helper_traverseIterable($group->invokeStep(new Input('foo')));
});

test('It returns the results of all steps when invoked', function () {
    $step1 = Mockery::mock(StepInterface::class);
    $step1->shouldReceive('addLogger')->once();
    $step1->shouldReceive('invokeStep')->once()->andReturn(helper_arrayToGenerator([new Output('1')]));
    $step2 = Mockery::mock(StepInterface::class);
    $step2->shouldReceive('addLogger')->once();
    $step2->shouldReceive('invokeStep')->once()->andReturn(helper_arrayToGenerator([new Output('2')]));
    $step3 = Mockery::mock(StepInterface::class);
    $step3->shouldReceive('addLogger')->once();
    $step3->shouldReceive('invokeStep')->once()->andReturn(helper_arrayToGenerator([new Output('3')]));

    $group = new ParallelGroup();
    $group->addLogger(new CliLogger());
    $group->addStep($step1)->addStep($step2)->addStep($step3);
    $result = $group->invokeStep(new Input('foo'));
    $result = helper_generatorToArray($result);

    expect($result)->toBeArray();
    expect($result)->toHaveCount(3);
    expect($result[0])->toBeInstanceOf(Output::class);
    expect($result[0]->get())->toBe('1');
    expect($result[1])->toBeInstanceOf(Output::class);
    expect($result[1]->get())->toBe('2');
    expect($result[2])->toBeInstanceOf(Output::class);
    expect($result[2]->get())->toBe('3');
});