<?php

namespace tests\Steps;

use Crwlr\Crawler\Input;
use Crwlr\Crawler\Logger\CliLogger;
use Crwlr\Crawler\Output;
use Crwlr\Crawler\Steps\SequentialGroup;
use Crwlr\Crawler\Steps\StepInterface;
use Mockery;

test('The factory method returns a Group object instance', function () {
    expect(SequentialGroup::new())->toBeInstanceOf(SequentialGroup::class);
});

test('You can add multiple steps and all steps where the previous step has an output are called', function () {
    $step1 = Mockery::mock(StepInterface::class);
    $step1->shouldReceive('addLogger')->once();
    $step1->shouldReceive('invokeStep')->once()->andReturn([new Output('one')]);
    $step2 = Mockery::mock(StepInterface::class);
    $step2->shouldReceive('addLogger')->once();
    $step2->shouldReceive('invokeStep')->once();
    $step3 = Mockery::mock(StepInterface::class);
    $step3->shouldReceive('addLogger')->once();
    $step3->shouldNotReceive('invokeStep');

    $group = new SequentialGroup();
    $group->addLogger(new CliLogger());
    $group->addStep($step1)->addStep($step2)->addStep($step3);
    $group->invokeStep(new Input('foo'));
});

test('It returns the results of all steps when invoked', function () {
    $step1 = Mockery::mock(StepInterface::class);
    $step1->shouldReceive('addLogger')->once();
    $step1->shouldReceive('invokeStep')->once()->andReturn([new Output('foo')]);
    $step2 = Mockery::mock(StepInterface::class);
    $step2->shouldReceive('addLogger')->once();
    $step2->shouldReceive('invokeStep')->once()->andReturn([new Output('bar')]);
    $step3 = Mockery::mock(StepInterface::class);
    $step3->shouldReceive('addLogger')->once();
    $step3->shouldReceive('invokeStep')->once()->andReturn([new Output('baz')]);

    $group = new SequentialGroup();
    $group->addLogger(new CliLogger());
    $group->addStep($step1)->addStep($step2)->addStep($step3);
    $result = $group->invokeStep(new Input('input'));

    expect($result)->toBeArray();
    expect($result)->toHaveCount(3);
    expect($result[0])->toBeInstanceOf(Output::class);
    expect($result[0]->get())->toBe('foo');
    expect($result[1])->toBeInstanceOf(Output::class);
    expect($result[1]->get())->toBe('bar');
    expect($result[2])->toBeInstanceOf(Output::class);
    expect($result[2]->get())->toBe('baz');
});

test('One step\'s output is the next step\'s input', function () {
    $step1 = Mockery::mock(StepInterface::class);
    $step1->shouldReceive('addLogger')->once();
    $step1->shouldReceive('invokeStep')->withArgs(function (Input $input) {
        return $input->get() === 'Initial Input';
    })->once()->andReturn([new Output('Step 1')]);
    $step2 = Mockery::mock(StepInterface::class);
    $step2->shouldReceive('addLogger')->once();
    $step2->shouldReceive('invokeStep')->withArgs(function (Input $input) {
        return $input->get() === 'Step 1';
    })->once()->andReturn([new Output('Step 2')]);

    $group = new SequentialGroup();
    $group->addLogger(new CliLogger());
    $group->addStep($step1)->addStep($step2);
    $group->invokeStep(new Input('Initial Input'));
});
