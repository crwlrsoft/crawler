<?php

namespace tests\Steps;

use Crwlr\Crawler\Crawler;
use Crwlr\Crawler\Input;
use Crwlr\Crawler\Loader\Http\HttpLoader;
use Crwlr\Crawler\Logger\CliLogger;
use Crwlr\Crawler\Output;
use Crwlr\Crawler\Result;
use Crwlr\Crawler\Steps\Group;
use Crwlr\Crawler\Steps\Loading\LoadingStepInterface;
use Crwlr\Crawler\Steps\Loop;
use Crwlr\Crawler\Steps\Step;
use Crwlr\Crawler\Steps\StepInterface;
use Crwlr\Crawler\UserAgents\BotUserAgent;
use Generator;
use Mockery;
use function tests\helper_arrayToGenerator;
use function tests\helper_generatorToArray;
use function tests\helper_getInputReturningStep;
use function tests\helper_getValueReturningStep;
use function tests\helper_invokeStepWithInput;
use function tests\helper_traverseIterable;

test('You can add a step and it passes on the logger', function () {
    $step = Mockery::mock(StepInterface::class);
    $step->shouldReceive('addLogger')->once();
    $step->shouldReceive('getResultKey');
    $step->shouldNotReceive('addLoader');
    $group = new Group();
    $group->addLogger(new CliLogger());
    $group->addStep($step);
});

test('It also passes on a new logger to all steps when the logger is added after the steps', function () {
    $step1 = Mockery::mock(StepInterface::class);
    $step1->shouldReceive('addLogger')->once();
    $step1->shouldReceive('getResultKey');
    $step2 = Mockery::mock(StepInterface::class);
    $step2->shouldReceive('addLogger')->once();
    $step2->shouldReceive('getResultKey');
    $group = new Group();
    $group->addStep($step1);
    $group->addStep($step2);
    $group->addLogger(new CliLogger());
});

test('It also passes on the loader to the step when addLoader method exists in step', function () {
    $step = Mockery::mock(LoadingStepInterface::class);
    $step->shouldReceive('addLogger')->once();
    $step->shouldReceive('addLoader')->once();
    $step->shouldReceive('getResultKey');
    $group = new Group();
    $group->addLogger(new CliLogger());
    $group->addLoader(new HttpLoader(new BotUserAgent('MyBot')));
    $group->addStep($step);
});

test('It also passes on a new loader to all steps when it is added after the steps', function () {
    $step1 = Mockery::mock(LoadingStepInterface::class);
    $step1->shouldReceive('addLoader')->once();
    $step1->shouldReceive('getResultKey');
    $step2 = Mockery::mock(LoadingStepInterface::class);
    $step2->shouldReceive('addLoader')->once();
    $step2->shouldReceive('getResultKey');
    $group = new Group();
    $group->addStep($step1);
    $group->addStep($step2);
    $group->addLoader(new HttpLoader(new BotUserAgent('MyBot')));
});

test('The factory method returns a Group object instance', function () {
    expect(Crawler::group())->toBeInstanceOf(Group::class);
});

test('You can add multiple steps and invokeStep calls all of them', function () {
    $step1 = Mockery::mock(StepInterface::class);
    $step1->shouldReceive('cascades', 'invokeStep')->once();
    $step2 = Mockery::mock(StepInterface::class);
    $step2->shouldReceive('cascades', 'invokeStep')->once();
    $step3 = Mockery::mock(StepInterface::class);
    $step3->shouldReceive('cascades', 'invokeStep')->once();

    $group = new Group();
    $group->addStep($step1)->addStep($step2)->addStep($step3);
    helper_traverseIterable($group->invokeStep(new Input('foo')));
});

test('It returns the results of all steps when invoked', function () {
    $step1 = Mockery::mock(StepInterface::class);

    $step1->shouldReceive('cascades')->once()->andReturn(true);

    $step1->shouldReceive('invokeStep')->once()->andReturn(helper_arrayToGenerator([new Output('1')]));

    $step2 = Mockery::mock(StepInterface::class);

    $step2->shouldReceive('cascades')->once()->andReturn(true);

    $step2->shouldReceive('invokeStep')->once()->andReturn(helper_arrayToGenerator([new Output('2')]));

    $step3 = Mockery::mock(StepInterface::class);

    $step3->shouldReceive('cascades')->once()->andReturn(true);

    $step3->shouldReceive('invokeStep')->once()->andReturn(helper_arrayToGenerator([new Output('3')]));

    $group = new Group();

    $group->addStep($step1)->addStep($step2)->addStep($step3);

    $output = $group->invokeStep(new Input('foo'));

    $output = helper_generatorToArray($output);

    expect($output)->toBeArray();

    expect($output)->toHaveCount(3);

    expect($output[0])->toBeInstanceOf(Output::class);

    expect($output[0]->get())->toBe('1');

    expect($output[1])->toBeInstanceOf(Output::class);

    expect($output[1]->get())->toBe('2');

    expect($output[2])->toBeInstanceOf(Output::class);

    expect($output[2]->get())->toBe('3');
});

test(
    'It combines the outputs of all it\'s steps into one output containing an array when combineToSingleOutput is used',
    function () {
        $step1 = helper_getValueReturningStep('lorem');

        $step2 = new class () extends Step {
            protected function invoke(mixed $input): Generator
            {
                yield 'ipsum';
                yield 'dolor';
            }
        };

        $step3 = helper_getValueReturningStep('sit');

        $group = new Group();

        $group->addStep($step1)->addStep($step2)->addStep($step3)->combineToSingleOutput();

        $output = helper_invokeStepWithInput($group, 'gogogo');

        expect($output)->toHaveCount(1);

        expect($output[0])->toBeInstanceOf(Output::class);

        expect($output[0]->get())->toBe(['lorem', ['ipsum', 'dolor'], 'sit']);
    }
);

test(
    'When mapping steps to the Result object and also combining to a single output, the resultKeys are also used in ' .
    'the output array',
    function () {
        $step1 = helper_getValueReturningStep('ich');

        $step2 = new class () extends Step {
            protected function invoke(mixed $input): Generator
            {
                yield 'bin';
                yield 'ein';
            }
        };

        $step3 = helper_getValueReturningStep('berliner');

        $group = (new Group())
            ->addStep('foo', $step1)
            ->addStep('bar', $step2)
            ->addStep('baz', $step3)
            ->combineToSingleOutput();

        $output = helper_invokeStepWithInput($group, 'https://www.gogo.go');

        expect($output)->toHaveCount(1);

        expect($output[0])->toBeInstanceOf(Output::class);

        $expectedOutputAndResultArray = ['foo' => 'ich', 'bar' => ['bin', 'ein'], 'baz' => 'berliner'];

        expect($output[0]->get())->toBe($expectedOutputAndResultArray);

        expect($output[0]->result)->toBeInstanceOf(Result::class);

        expect($output[0]->result->toArray())->toBe($expectedOutputAndResultArray); // @phpstan-ignore-line
    }
);

test('It doesn\'t output anything when the dontCascade method was called', function () {
    $step1 = helper_getValueReturningStep('something');

    $step2 = new class () extends Step {
        protected function invoke(mixed $input): Generator
        {
            foreach ([1, 2, 3, 4, 5, 6, 7, 8, 9, 10] as $number) {
                yield $number;
            }
        }
    };

    $group = (new Group())
        ->addStep('foo', $step1)
        ->addStep('bar', $step2);

    expect(helper_invokeStepWithInput($group, 'foo'))->toHaveCount(11);

    $group->dontCascade();

    expect(helper_invokeStepWithInput($group, 'foo'))->toHaveCount(0);

    // Also doesn't yield when a step is added after the dontCascade() call
    $group->addStep(helper_getValueReturningStep('something'));

    expect(helper_invokeStepWithInput($group, 'foo'))->toHaveCount(0);
});

test('It doesn\'t return the output of a step when the dontCascade method was called on that step', function () {
    $step1 = helper_getValueReturningStep('foo');

    $step2 = helper_getValueReturningStep('bar')->dontCascade();

    $group = (new Group())
        ->addStep('foo', $step1)
        ->addStep($step2);

    $outputs = helper_invokeStepWithInput($group, 'foo');

    expect($outputs)->toHaveCount(1);

    expect($outputs[0]->get())->toBe('foo');
});

test(
    'It doesn\'t contain the output of a step when the dontCascade method was called on that step and the Group\'s ' .
    'output is combined',
    function () {
        $step1 = helper_getValueReturningStep('abc');

        $step2 = helper_getValueReturningStep('def')->dontCascade();

        $group = (new Group())
            ->addStep('one', $step1)
            ->addStep('two', $step2)
            ->combineToSingleOutput();

        $outputs = helper_invokeStepWithInput($group, 'foo');

        expect($outputs)->toHaveCount(1);

        expect($outputs[0]->get())->toBe(['one' => 'abc']);
    }
);

test('You can update the input for further steps with the output of a step that is before those steps', function () {
    $step1 = helper_getValueReturningStep(' rocks')
        ->updateInputUsingOutput(function (mixed $input, mixed $output) {
            return $input . $output;
        });

    $step2 = helper_getInputReturningStep();

    $group = (new Group())
        ->addStep($step1)
        ->addStep($step2);

    $outputs = helper_invokeStepWithInput($group, 'crwlr.software');

    expect($outputs)->toHaveCount(2);

    expect($outputs[1]->get())->toBe('crwlr.software rocks');
});

test('Updating the input for further steps with output also works with loop steps', function () {
    $step1 = helper_getValueReturningStep(' Jump!')
        ->updateInputUsingOutput(function (mixed $input, mixed $output) {
            return $input . $output;
        });

    $step1 = (new Loop($step1))->maxIterations(2);

    $step2 = helper_getInputReturningStep();

    $group = (new Group())
        ->addStep($step1)
        ->addStep($step2);

    $outputs = helper_invokeStepWithInput($group, 'The Mac Dad will make ya:');

    expect($outputs)->toHaveCount(3);

    expect($outputs[2]->get())->toBe('The Mac Dad will make ya: Jump! Jump!');
});

test('Updating the input for further steps also works when combining the group output to a single output', function () {
    $step1 = helper_getValueReturningStep(' Jump!')
        ->updateInputUsingOutput(function (mixed $input, mixed $output) {
            return $input . $output;
        });

    $step1 = (new Loop($step1))->maxIterations(2);

    $group = (new Group())
        ->addStep($step1)
        ->addStep(helper_getInputReturningStep())
        ->combineToSingleOutput();

    $outputs = helper_invokeStepWithInput($group, 'The Mac Dad will make ya:');

    expect($outputs)->toHaveCount(1);

    expect($outputs[0]->get())->toBe([
        [' Jump!', ' Jump!'],
        'The Mac Dad will make ya: Jump! Jump!'
    ]);
});

it('knows when at least one of the steps adds something to the final result', function () {
    $step1 = helper_getValueReturningStep('Tick');

    $step2 = helper_getValueReturningStep('Trick');

    $step3 = helper_getValueReturningStep('Track');

    $group = (new Group())
        ->addStep($step1)
        ->addStep($step2)
        ->addStep('foo', $step3);

    expect($group->addsToOrCreatesResult())->toBe(true);

    $outputs = helper_invokeStepWithInput($group, 'ducks');

    expect($outputs)->toHaveCount(3);

    expect($outputs[0]->result)->toBeNull();

    expect($outputs[1]->result)->toBeNull();

    expect($outputs[2]->result)->toBeInstanceOf(Result::class);

    expect($outputs[2]->result->get('foo'))->toBe('Track'); // @phpstan-ignore-line
});

it('knows when at least one of the steps adds something to the final result when addKeysToResult is used', function () {
    $step1 = helper_getValueReturningStep('Tick');

    $step2 = helper_getValueReturningStep('Trick');

    $step3 = helper_getValueReturningStep(['duck' => 'Track'])->addKeysToResult();

    $group = (new Group())
        ->addStep($step1)
        ->addStep($step2)
        ->addStep($step3);

    expect($group->addsToOrCreatesResult())->toBe(true);

    $outputs = helper_invokeStepWithInput($group, 'ducks');

    expect($outputs)->toHaveCount(3);

    expect($outputs[0]->result)->toBeNull();

    expect($outputs[1]->result)->toBeNull();

    expect($outputs[2]->result)->toBeInstanceOf(Result::class);

    expect($outputs[2]->result->get('duck'))->toBe('Track'); // @phpstan-ignore-line
});
