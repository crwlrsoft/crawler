<?php

namespace tests\Steps;

use Closure;
use Crwlr\Crawler\Crawler;
use Crwlr\Crawler\Input;
use Crwlr\Crawler\Loader\Http\HttpLoader;
use Crwlr\Crawler\Logger\CliLogger;
use Crwlr\Crawler\Output;
use Crwlr\Crawler\Result;
use Crwlr\Crawler\Steps\Group;
use Crwlr\Crawler\Steps\Loading\LoadingStepInterface;
use Crwlr\Crawler\Steps\Refiners\StringRefiner;
use Crwlr\Crawler\Steps\Step;
use Crwlr\Crawler\Steps\StepInterface;
use Crwlr\Crawler\UserAgents\BotUserAgent;
use Crwlr\Crawler\UserAgents\UserAgent;
use Exception;
use Generator;
use Mockery;

use tests\_Stubs\LoaderCollectingStep;
use tests\_Stubs\PhantasyLoader;

use function tests\helper_getInputReturningStep;
use function tests\helper_getStdClassWithData;
use function tests\helper_getStepYieldingObjectWithNumber;
use function tests\helper_getValueReturningStep;
use function tests\helper_invokeStepWithInput;

function helper_addStepsToGroup(Group $group, Step ...$steps): Group
{
    foreach ($steps as $step) {
        $group->addStep($step);
    }

    return $group;
}

function helper_addUpdateInputUsingOutputCallbackToSteps(Closure $callback, Step ...$steps): void
{
    foreach ($steps as $step) {
        $step->updateInputUsingOutput($callback);
    }
}

function helper_getStepThatRemembersIfItWasCalled(): Step
{
    return new class () extends Step {
        public bool $called = false;

        protected function invoke(mixed $input): Generator
        {
            $this->called = true;

            yield 'test';
        }
    };
}

test('You can add a step and it passes on the logger', function () {
    $step = Mockery::mock(StepInterface::class);

    $step->shouldReceive('addLogger')->once();

    $step->shouldNotReceive('addLoader');

    $group = new Group();

    $group->addLogger(new CliLogger());

    $group->addStep($step);
});

it('also passes on a new logger to all steps when the logger is added after the steps', function () {
    $step1 = Mockery::mock(StepInterface::class);

    $step1->shouldReceive('addLogger')->once();

    $step2 = Mockery::mock(StepInterface::class);

    $step2->shouldReceive('addLogger')->once();

    $group = new Group();

    $group->addStep($step1);

    $group->addStep($step2);

    $group->addLogger(new CliLogger());
});

it('also passes on the loader to the step when addLoader method exists in step', function () {
    $step = Mockery::mock(LoadingStepInterface::class);

    $step->shouldReceive('addLogger')->once();

    $step->shouldReceive('addLoader')->once();

    $group = new Group();

    $group->addLogger(new CliLogger());

    $group->addLoader(new HttpLoader(new BotUserAgent('MyBot')));

    $group->addStep($step);
});

it('also passes on a new loader to all steps when it is added after the steps', function () {
    $step1 = Mockery::mock(LoadingStepInterface::class);

    $step1->shouldReceive('addLoader')->once();

    $step2 = Mockery::mock(LoadingStepInterface::class);

    $step2->shouldReceive('addLoader')->once();

    $group = new Group();

    $group->addStep($step1);

    $group->addStep($step2);

    $group->addLoader(new HttpLoader(new BotUserAgent('MyBot')));
});

test('you can add multiple loaders', function () {
    $group = new Group();

    $loaders = [
        'http' => new HttpLoader(new UserAgent('Youseragent')),
        'phantasy' => new PhantasyLoader(new UserAgent('Youseragent')),
    ];

    $step = new LoaderCollectingStep();

    $group->addStep($step);

    $group->addLoaders($loaders);

    expect($step->loaders)->toHaveCount(2);
});

test('The factory method returns a Group object instance', function () {
    expect(Crawler::group())->toBeInstanceOf(Group::class);
});

test('You can add multiple steps and invokeStep calls all of them', function () {
    $step1 = helper_getStepThatRemembersIfItWasCalled();

    $step2 = helper_getStepThatRemembersIfItWasCalled();

    $step3 = helper_getStepThatRemembersIfItWasCalled();

    $group = new Group();

    $group->addStep($step1)->addStep($step2)->addStep($step3);

    helper_invokeStepWithInput($group);

    expect($step1->called)->toBeTrue(); // @phpstan-ignore-line

    expect($step2->called)->toBeTrue(); // @phpstan-ignore-line

    expect($step3->called)->toBeTrue(); // @phpstan-ignore-line
});

it('combines the outputs of all it\'s steps into one output containing an array', function () {
    $step1 = helper_getValueReturningStep('lorem');

    $step2 = new class () extends Step {
        protected function invoke(mixed $input): Generator
        {
            yield 'ipsum';
        }
    };

    $step3 = helper_getValueReturningStep('dolor');

    $group = new Group();

    $group->addStep($step1)->addStep($step2)->addStep($step3);

    $output = helper_invokeStepWithInput($group, 'gogogo');

    expect($output)->toHaveCount(1);

    expect($output[0])->toBeInstanceOf(Output::class);

    expect($output[0]->get())->toBe(['lorem', 'ipsum', 'dolor']);
});

test(
    'When defining keys for the steps as first param in addStep call, the combined output array has those keys',
    function () {
        $step1 = helper_getValueReturningStep('ich');

        $step2 = new class () extends Step {
            protected function invoke(mixed $input): Generator
            {
                yield 'bin';
            }
        };

        $step3 = helper_getValueReturningStep('ein berliner');

        $group = (new Group())
            ->addStep('foo', $step1)
            ->addStep('bar', $step2)
            ->addStep('baz', $step3);

        $output = helper_invokeStepWithInput($group, 'https://www.gogo.go');

        expect($output)->toHaveCount(1);

        expect($output[0])->toBeInstanceOf(Output::class);

        $expectedOutputAndResultArray = ['foo' => 'ich', 'bar' => 'bin', 'baz' => 'ein berliner'];

        expect($output[0]->get())->toBe($expectedOutputAndResultArray);
    }
);

it('merges array outputs with string keys to one array', function () {
    $step1 = helper_getValueReturningStep(['foo' => 'fooValue', 'bar' => 'barValue']);

    $step2 = helper_getValueReturningStep(['baz' => 'bazValue', 'yo' => 'lo']);

    $group = (new Group())
        ->addStep($step1)
        ->addStep($step2);

    $output = helper_invokeStepWithInput($group);

    expect($output)->toHaveCount(1);

    expect($output[0]->get())->toBe([
        'foo' => 'fooValue',
        'bar' => 'barValue',
        'baz' => 'bazValue',
        'yo' => 'lo',
    ]);
});

it('doesn\'t invoke twice with duplicate inputs when uniqueInput was called', function () {
    $step1 = helper_getValueReturningStep('one');

    $step2 = helper_getValueReturningStep('two');

    $group = helper_addStepsToGroup(new Group(), $step1, $step2);

    $outputs = helper_invokeStepWithInput($group, 'foo');

    expect($outputs)->toHaveCount(1);

    $outputs = helper_invokeStepWithInput($group, 'foo');

    expect($outputs)->toHaveCount(1);

    $group->resetAfterRun();

    $group->uniqueInputs();

    $outputs = helper_invokeStepWithInput($group, 'foo');

    expect($outputs)->toHaveCount(1);

    $outputs = helper_invokeStepWithInput($group, 'foo');

    expect($outputs)->toHaveCount(0);
});

it(
    'doesn\'t invoke twice with array inputs with duplicate keys when uniqueInput was called with that key',
    function () {
        $step1 = helper_getValueReturningStep('one');

        $step2 = helper_getValueReturningStep('two');

        $group = helper_addStepsToGroup(new Group(), $step1, $step2);

        $group->uniqueInputs();

        $outputs = helper_invokeStepWithInput($group, ['foo' => 'bar', 'bttfc' => 'marty']);

        expect($outputs)->toHaveCount(1);

        $outputs = helper_invokeStepWithInput($group, ['foo' => 'bar', 'bttfc' => 'doc']);

        expect($outputs)->toHaveCount(1);

        $group->resetAfterRun();

        $group->uniqueInputs('foo');

        $outputs = helper_invokeStepWithInput($group, ['foo' => 'bar', 'bttfc' => 'marty']);

        expect($outputs)->toHaveCount(1);

        $outputs = helper_invokeStepWithInput($group, ['foo' => 'bar', 'bttfc' => 'doc']);

        expect($outputs)->toHaveCount(0);
    }
);

it(
    'doesn\'t invoke twice with object inputs with duplicate keys when uniqueInput was called with that key',
    function () {
        $step1 = helper_getValueReturningStep('one');

        $step2 = helper_getValueReturningStep('two');

        $group = helper_addStepsToGroup(new Group(), $step1, $step2);

        $group->uniqueInputs();

        $outputs = helper_invokeStepWithInput($group, helper_getStdClassWithData(['foo' => 'bar', 'bttfc' => 'marty']));

        expect($outputs)->toHaveCount(1);

        $outputs = helper_invokeStepWithInput($group, helper_getStdClassWithData(['foo' => 'bar', 'bttfc' => 'doc']));

        expect($outputs)->toHaveCount(1);

        $group->resetAfterRun();

        $group->uniqueInputs('foo');

        $outputs = helper_invokeStepWithInput($group, helper_getStdClassWithData(['foo' => 'bar', 'bttfc' => 'marty']));

        expect($outputs)->toHaveCount(1);

        $outputs = helper_invokeStepWithInput($group, helper_getStdClassWithData(['foo' => 'bar', 'bttfc' => 'doc']));

        expect($outputs)->toHaveCount(0);
    }
);

it('returns only unique outputs when uniqueOutput was called', function () {
    $step1 = helper_getInputReturningStep();

    $step2 = helper_getValueReturningStep('test');

    $group = helper_addStepsToGroup(new Group(), $step1, $step2)->uniqueOutputs();

    $outputs = helper_invokeStepWithInput($group, 'foo');

    expect($outputs)->toHaveCount(1);

    $outputs = helper_invokeStepWithInput($group, 'bar');

    expect($outputs)->toHaveCount(1);

    $outputs = helper_invokeStepWithInput($group, 'foo');

    expect($outputs)->toHaveCount(0);
});

it('returns only unique outputs when outputs are arrays and uniqueOutput was called', function () {
    $step1 = helper_getInputReturningStep();

    $step2 = helper_getValueReturningStep(['lorem' => 'ipsum']);

    $group = helper_addStepsToGroup(new Group(), $step1, $step2)->uniqueOutputs();

    $outputs = helper_invokeStepWithInput($group, ['foo' => 'bar']);

    expect($outputs)->toHaveCount(1);

    $outputs = helper_invokeStepWithInput($group, ['baz' => 'quz']);

    expect($outputs)->toHaveCount(1);

    $outputs = helper_invokeStepWithInput($group, ['foo' => 'bar']);

    expect($outputs)->toHaveCount(0);
});

it(
    'returns only unique outputs when outputs are arrays and uniqueOutput was called with a key from the output arrays',
    function () {
        $step1 = helper_getInputReturningStep();

        $step2 = helper_getValueReturningStep(['lorem' => 'ipsum']);

        $group = helper_addStepsToGroup(new Group(), $step1, $step2)->uniqueOutputs('foo');

        $outputs = helper_invokeStepWithInput($group, ['foo' => 'bar']);

        expect($outputs)->toHaveCount(1);

        $outputs = helper_invokeStepWithInput($group, ['foo' => 'baz']);

        expect($outputs)->toHaveCount(1);

        $outputs = helper_invokeStepWithInput($group, ['foo' => 'bar', 'something' => 'else']);

        expect($outputs)->toHaveCount(0);
    }
);

it('returns only unique outputs when outputs are objects and uniqueOutput was called', function () {
    $step1 = helper_getStepYieldingObjectWithNumber(10);

    $step2 = helper_getStepYieldingObjectWithNumber(11);

    $group = helper_addStepsToGroup(new Group(), $step1, $step2);

    expect(helper_invokeStepWithInput($group))->toHaveCount(1);

    $group->uniqueOutputs();

    expect(helper_invokeStepWithInput($group))->toHaveCount(1);

    expect(helper_invokeStepWithInput($group))->toHaveCount(0);

    $incrementNumberCallback = function (mixed $input) {
        return $input+1;
    };

    helper_addUpdateInputUsingOutputCallbackToSteps($incrementNumberCallback, $step1, $step2);

    expect(helper_invokeStepWithInput($group, new Input(1)))->toHaveCount(1);
});

it(
    'returns only unique outputs when outputs are objects and uniqueOutput was called with a property name from the ' .
    'output objects',
    function () {
        $step1 = helper_getStepYieldingObjectWithNumber(21);

        $step2 = helper_getStepYieldingObjectWithNumber(23);

        $group = helper_addStepsToGroup(new Group(), $step1, $step2);

        expect(helper_invokeStepWithInput($group))->toHaveCount(1);

        $group->resetAfterRun();

        $group->uniqueOutputs('number');

        expect(helper_invokeStepWithInput($group))->toHaveCount(1);

        expect(helper_invokeStepWithInput($group))->toHaveCount(0);

        $group->resetAfterRun();

        $incrementNumberCallback = function (mixed $input) {
            return $input+1;
        };

        helper_addUpdateInputUsingOutputCallbackToSteps($incrementNumberCallback, $step1, $step2);

        expect(helper_invokeStepWithInput($group, new Input(1)))->toHaveCount(1);
    }
);

it(
    'excludes the output of a step from the combined group output, when the excludeFromGroupOutput() method was called',
    function () {
        $step1 = helper_getValueReturningStep(['foo' => 'one']);

        $step2 = helper_getValueReturningStep(['bar' => 'two'])->excludeFromGroupOutput();

        $step3 = helper_getValueReturningStep(['baz' => 'three']);

        $group = helper_addStepsToGroup(new Group(), $step1, $step2, $step3);

        $outputs = helper_invokeStepWithInput($group);

        expect($outputs)->toHaveCount(1);

        expect($outputs[0]->get())->toBe(['foo' => 'one', 'baz' => 'three']);
    }
);

test('You can update the input for further steps with the output of a step that is before those steps', function () {
    $step1 = helper_getValueReturningStep(' rocks')
        ->updateInputUsingOutput(function (mixed $input, mixed $output) {
            return $input . $output;
        });

    $step2 = helper_getInputReturningStep();

    $group = (new Group())
        ->addStep('foo', $step1)
        ->addStep('bar', $step2);

    $outputs = helper_invokeStepWithInput($group, 'crwlr.software');

    expect($outputs)->toHaveCount(1);

    expect($outputs[0]->get())->toBe(['foo' => ' rocks', 'bar' => 'crwlr.software rocks']);
});

it('knows when at least one of the steps adds something to the final result', function () {
    $step1 = helper_getValueReturningStep('Tick');

    $step2 = helper_getValueReturningStep('Trick');

    $step3 = helper_getValueReturningStep('Track')->addToResult('foo');

    $group = (new Group())
        ->addStep($step1)
        ->addStep($step2);

    expect($group->addsToOrCreatesResult())->toBeFalse();

    $group->addStep($step3);

    expect($group->addsToOrCreatesResult())->toBeTrue();

    $outputs = helper_invokeStepWithInput($group, 'ducks');

    expect($outputs)->toHaveCount(1);

    expect($outputs[0]->get())->toBe(['Tick', 'Trick', 'Track']);

    expect($outputs[0]->result?->toArray())->toBe(['foo' => 'Track']);
});

it(
    'knows when at least one of the steps adds something to the final result when addToResult is used without argument',
    function () {
        $step1 = helper_getValueReturningStep('Tick');

        $step2 = helper_getValueReturningStep('Trick');

        $step3 = helper_getValueReturningStep(['duck' => 'Track'])->addToResult();

        $group = (new Group())
            ->addStep($step1)
            ->addStep($step2)
            ->addStep($step3);

        expect($group->addsToOrCreatesResult())->toBe(true);

        $outputs = helper_invokeStepWithInput($group, 'ducks');

        expect($outputs)->toHaveCount(1);

        expect($outputs[0]->get())->toBe(['Tick', 'Trick', 'duck' => 'Track']);

        expect($outputs[0]->result?->toArray())->toBe(['duck' => 'Track']);
    }
);

test(
    'addsToOrCreatesResult() returns true when addLaterToResult() was called',
    function () {
        $step1 = helper_getValueReturningStep('Tick');

        $step2 = helper_getValueReturningStep('Trick');

        $step3 = helper_getValueReturningStep(['duck' => 'Track'])->addLaterToResult();

        $group = (new Group())
            ->addStep($step1)
            ->addStep($step2)
            ->addStep($step3);

        expect($group->addsToOrCreatesResult())->toBe(true);
    }
);

test(
    'createsResult() returns true when addToResult() was called',
    function () {
        $step1 = helper_getValueReturningStep('Tick');

        $step2 = helper_getValueReturningStep('Trick');

        $step3 = helper_getValueReturningStep(['duck' => 'Track']);

        $group = (new Group())
            ->addStep($step1)
            ->addStep($step2)
            ->addStep($step3)
            ->addToResult(['duck']);

        expect($group->createsResult())->toBe(true);
    }
);

test(
    'createsResult() returns false when addLaterToResult() was called',
    function () {
        $step1 = helper_getValueReturningStep('Tick');

        $step2 = helper_getValueReturningStep('Trick');

        $step3 = helper_getValueReturningStep(['duck' => 'Track'])->addLaterToResult();

        $group = (new Group())
            ->addStep($step1)
            ->addStep($step2)
            ->addStep($step3);

        expect($group->createsResult())->toBeFalse();
    }
);

it('uses a key from array input when defined', function () {
    $step = helper_getInputReturningStep();

    $group = (new Group())
        ->addStep('test', $step)
        ->useInputKey('bar');

    $outputs = helper_invokeStepWithInput($group, new Input(
        ['foo' => 'fooValue', 'bar' => 'barValue', 'baz' => 'bazValue']
    ));

    expect($outputs)->toHaveCount(1);

    expect($outputs[0]->get())->toBe(['test' => 'barValue']);
});

it(
    'adds the combined output to result with a certain key when addToResult() is used with a key as argument',
    function () {
        $step1 = helper_getValueReturningStep('foo');

        $step2 = helper_getValueReturningStep('bar');

        $group = (new Group())
            ->addStep('key1', $step1)
            ->addStep('key2', $step2)
            ->addToResult('test');

        $output = helper_invokeStepWithInput($group);

        expect($output)->toHaveCount(1);

        expect($output[0]->result)->toBeInstanceOf(Result::class);

        expect($output[0]->result?->toArray())->toBe(['test' => ['key1' => 'foo', 'key2' => 'bar']]);
    }
);

it(
    'adds all keys from a combined array output to the Result when addToResult() was called without argument',
    function () {
        $step1 = helper_getValueReturningStep(['foo' => 'fooValue', 'bar' => 'barValue']);

        $step2 = helper_getValueReturningStep(['baz' => 'bazValue', 'yo' => 'lo']);

        $group = (new Group())
            ->addStep($step1)
            ->addStep($step2)
            ->addToResult();

        $output = helper_invokeStepWithInput($group);

        expect($output)->toHaveCount(1);

        expect($output[0]->result)->toBeInstanceOf(Result::class);

        expect($output[0]->result?->toArray())->toBe([
            'foo' => 'fooValue',
            'bar' => 'barValue',
            'baz' => 'bazValue',
            'yo' => 'lo',
        ]);
    }
);

it(
    'adds all defined keys from a combined array output to the Result when addToResult() was called with argument',
    function () {
        $step1 = helper_getValueReturningStep(['foo' => 'fooValue', 'bar' => 'barValue']);

        $step2 = helper_getValueReturningStep(['baz' => 'bazValue', 'yo' => 'lo']);

        $group = (new Group())
            ->addStep($step1)
            ->addStep($step2)
            ->addToResult(['foo', 'baz', 'yo']);

        $output = helper_invokeStepWithInput($group);

        expect($output)->toHaveCount(1);

        expect($output[0]->result)->toBeInstanceOf(Result::class);

        expect($output[0]->result?->toArray())->toBe([
            'foo' => 'fooValue',
            'baz' => 'bazValue',
            'yo' => 'lo',
        ]);
    }
);

it(
    'adds a secondary Result object with data to add later to main Result objects when addLaterToResult() is called',
    function () {
        $step1 = helper_getValueReturningStep(['foo' => 'one', 'bar' => 'two']);

        $step2 = helper_getValueReturningStep(['baz' => 'three', 'four' => 'quz']);

        $group = (new Group())
            ->addStep($step1)
            ->addStep($step2)
            ->addLaterToResult(['foo', 'baz']);

        $outputs = helper_invokeStepWithInput($group);

        expect($outputs[0]->result)->toBeNull();

        expect($outputs[0]->addLaterToResult)->toBeInstanceOf(Result::class);

        expect($outputs[0]->addLaterToResult?->toArray())->toBe(['foo' => 'one', 'baz' => 'three']);
    }
);

it(
    'adds a secondary Result object with data to add later to main Result objects when addLaterToResult() is called ' .
    'on one of the steps in the group',
    function () {
        $step1 = helper_getValueReturningStep(['foo' => 'one', 'bar' => 'two'])->addLaterToResult();

        $step2 = helper_getValueReturningStep(['baz' => 'three', 'four' => 'quz']);

        $group = (new Group())
            ->addStep($step1)
            ->addStep($step2);

        $outputs = helper_invokeStepWithInput($group);

        expect($outputs[0]->result)->toBeNull();

        expect($outputs[0]->addLaterToResult)->toBeInstanceOf(Result::class);

        expect($outputs[0]->addLaterToResult?->toArray())->toBe(['foo' => 'one', 'bar' => 'two']);
    }
);

test(
    'When steps yield multiple outputs it combines the first output from first step with first output from second ' .
        'step and so on.',
    function () {
        $step1 = new class () extends Step {
            protected function invoke(mixed $input): Generator
            {
                yield ['one' => 'foo'];

                yield ['two' => 'bar'];
            }
        };

        $step2 = new class () extends Step {
            protected function invoke(mixed $input): Generator
            {
                yield ['three' => 'baz'];

                yield ['four' => 'quz'];
            }
        };

        $group = (new Group())
            ->addStep($step1)
            ->addStep($step2);

        $output = helper_invokeStepWithInput($group);

        expect($output)->toHaveCount(2);

        expect($output[0]->get())->toBe(['one' => 'foo', 'three' => 'baz']);

        expect($output[1]->get())->toBe(['two' => 'bar', 'four' => 'quz']);
    }
);

it('ignores the key set via outputKey because group step output is always an array', function () {
    $step1 = new class () extends Step {
        protected function invoke(mixed $input): Generator
        {
            yield ['one' => 'foo'];
        }
    };

    $step2 = new class () extends Step {
        protected function invoke(mixed $input): Generator
        {
            yield ['two' => 'bar'];
        }
    };

    $group = (new Group())
        ->addStep($step1)
        ->addStep($step2)
        ->outputKey('baz');

    $output = helper_invokeStepWithInput($group);

    expect($output)->toHaveCount(1);

    expect($output[0]->get())->toBe(['one' => 'foo', 'two' => 'bar']);
});

it(
    'keeps input data in output when keepInputData() was called when outputs are combined',
    function () {
        $step1 = new class () extends Step {
            protected function invoke(mixed $input): Generator
            {
                yield ['foo' => 'one'];
            }
        };

        $step2 = new class () extends Step {
            protected function invoke(mixed $input): Generator
            {
                yield ['bar' => 'two'];
            }
        };

        $group = (new Group())
            ->addStep($step1)
            ->addStep($step2)
            ->keepInputData();

        $output = helper_invokeStepWithInput($group, new Input(['baz' => 'three']));

        expect($output)->toHaveCount(1);

        expect($output[0]->get())->toBe(['foo' => 'one', 'bar' => 'two', 'baz' => 'three']);
    }
);

it('keeps non array input data in array output with key', function () {
    $step1 = new class () extends Step {
        protected function invoke(mixed $input): Generator
        {
            yield ['foo' => 'one'];
        }
    };

    $step2 = new class () extends Step {
        protected function invoke(mixed $input): Generator
        {
            yield ['bar' => 'two'];
        }
    };

    $group = (new Group())
        ->addStep($step1)
        ->addStep($step2)
        ->keepInputData('baz');

    $output = helper_invokeStepWithInput($group, new Input('three'));

    expect($output)->toHaveCount(1);

    expect($output[0]->get())->toBe(['foo' => 'one', 'bar' => 'two', 'baz' => 'three']);
});

it('throws an error when non array input should be kept but no key is defined', function () {
    $step1 = new class () extends Step {
        protected function invoke(mixed $input): Generator
        {
            yield ['foo' => 'one'];
        }
    };

    $step2 = new class () extends Step {
        protected function invoke(mixed $input): Generator
        {
            yield ['bar' => 'two'];
        }
    };

    $group = (new Group())
        ->addStep($step1)
        ->addStep($step2)
        ->keepInputData();

    helper_invokeStepWithInput($group, new Input('three'));
})->throws(Exception::class);

it('does not replace output data when a key from input to keep is also defined in output', function () {
    $step1 = new class () extends Step {
        protected function invoke(mixed $input): Generator
        {
            yield ['foo' => 'one'];
        }
    };

    $step2 = new class () extends Step {
        protected function invoke(mixed $input): Generator
        {
            yield ['bar' => 'two'];
        }
    };

    $group = (new Group())
        ->addStep($step1)
        ->addStep($step2)
        ->keepInputData();

    $output = helper_invokeStepWithInput($group, new Input(['foo' => 'four', 'baz' => 'three']));

    expect($output)->toHaveCount(1);

    expect($output[0]->get())->toBe(['foo' => 'one', 'bar' => 'two', 'baz' => 'three']);
});

it('contains an element with a numeric key when it contains a step that yields non array output', function () {
    $step1 = new class () extends Step {
        protected function invoke(mixed $input): Generator
        {
            yield 'one';
        }
    };

    $step2 = new class () extends Step {
        protected function invoke(mixed $input): Generator
        {
            yield ['bar' => 'two'];
        }
    };

    $group = (new Group())
        ->addStep($step1)
        ->addStep($step2)
        ->keepInputData();

    $output = helper_invokeStepWithInput($group, new Input(['baz' => 'three']));

    expect($output)->toHaveCount(1);

    expect($output[0]->get())->toBe([0 => 'one', 'bar' => 'two', 'baz' => 'three']);
});

it('keeps array input data when some output is non array but converted to array using outputKey()', function () {
    $step1 = new class () extends Step {
        protected function invoke(mixed $input): Generator
        {
            yield 'one';
        }
    };

    $step2 = new class () extends Step {
        protected function invoke(mixed $input): Generator
        {
            yield ['bar' => 'two'];
        }
    };

    $group = (new Group())
        ->addStep($step1->outputKey('foo'))
        ->addStep($step2)
        ->keepInputData();

    $output = helper_invokeStepWithInput($group, new Input(['baz' => 'three']));

    expect($output)->toHaveCount(1);

    expect($output[0]->get())->toBe(['foo' => 'one', 'bar' => 'two', 'baz' => 'three']);
});

it('throws an exception when input should be kept, is non array and no key is defined', function () {
    $step1 = new class () extends Step {
        protected function invoke(mixed $input): Generator
        {
            yield 'one';
        }
    };

    $step2 = new class () extends Step {
        protected function invoke(mixed $input): Generator
        {
            yield ['bar' => 'two'];
        }
    };

    $group = (new Group())
        ->addStep($step1)
        ->addStep($step2)
        ->keepInputData();

    helper_invokeStepWithInput($group, new Input('three'));
})->throws(Exception::class);

it('keeps the original input data when useInputKey() is used', function () {
    $step1 = new class () extends Step {
        protected function invoke(mixed $input): Generator
        {
            yield ['foo' => 'one'];
        }
    };

    $step2 = new class () extends Step {
        protected function invoke(mixed $input): Generator
        {
            yield ['bar' => 'two'];
        }
    };

    $group = (new Group())
        ->addStep($step1)
        ->addStep($step2)
        ->useInputKey('baz')
        ->keepInputData();

    $output = helper_invokeStepWithInput($group, new Input(['baz' => 'three', 'quz' => 'four']));

    expect($output)->toHaveCount(1);

    expect($output[0]->get())->toBe(['foo' => 'one', 'bar' => 'two', 'baz' => 'three', 'quz' => 'four']);
});

it('applies a Closure refiner to the steps output', function () {
    $step1 = helper_getValueReturningStep(['foo' => 'one']);

    $step2 = helper_getValueReturningStep(['bar' => 'two']);

    $group = (new Group())
        ->addStep($step1)
        ->addStep($step2)
        ->refineOutput(function (mixed $outputValue) {
            $outputValue['baz'] = 'three';

            $outputValue['bar'] .= ' refined';

            return $outputValue;
        });

    $outputs = helper_invokeStepWithInput($group);

    expect($outputs[0]->get())->toBe(['foo' => 'one', 'bar' => 'two refined', 'baz' => 'three']);
});

it('applies an instance of the RefinerInterface to the steps output', function () {
    $step1 = helper_getValueReturningStep(['foo' => 'lorem ipsum dolor']);

    $step2 = helper_getValueReturningStep(['bar' => 'two']);

    $group = (new Group())
        ->addStep($step1)
        ->addStep($step2)
        ->refineOutput('foo', StringRefiner::betweenFirst('lorem', 'dolor'));

    $outputs = helper_invokeStepWithInput($group);

    expect($outputs[0]->get())->toBe(['foo' => 'ipsum', 'bar' => 'two']);
});

it('applies multiple refiners to the steps output in the order they\'re added', function () {
    $step1 = helper_getValueReturningStep(['foo' => 'lorem ipsum dolor']);

    $step2 = helper_getValueReturningStep(['bar' => 'two']);

    $group = (new Group())
        ->addStep($step1)
        ->addStep($step2)
        ->refineOutput('foo', StringRefiner::betweenFirst('lorem', 'dolor'))
        ->refineOutput('bar', fn (mixed $outputValue) => $outputValue . ' refined');

    $outputs = helper_invokeStepWithInput($group);

    expect($outputs[0]->get())->toBe(['foo' => 'ipsum', 'bar' => 'two refined']);
});

test('you can apply multiple refiners to the same output array key', function () {
    $step1 = helper_getValueReturningStep(['foo' => 'lorem ipsum dolor']);

    $step2 = helper_getValueReturningStep(['bar' => 'two']);

    $group = (new Group())
        ->addStep($step1)
        ->addStep($step2)
        ->refineOutput('foo', StringRefiner::betweenFirst('lorem', 'dolor'))
        ->refineOutput('foo', fn (mixed $outputValue) => $outputValue . ' refined');

    $outputs = helper_invokeStepWithInput($group);

    expect($outputs[0]->get())->toBe(['foo' => 'ipsum refined', 'bar' => 'two']);
});

it(
    'uses the original input value when applying a refiner, not only the value of an input array key chosen via ' .
    'useInputKey()',
    function () {
        $step1 = helper_getValueReturningStep(['foo' => 'one']);

        $step2 = helper_getValueReturningStep(['bar' => 'two']);

        $group = (new Group())
            ->addStep($step1)
            ->addStep($step2)
            ->refineOutput(fn (mixed $outputValue, mixed $originalInputValue) => $originalInputValue);

        $outputs = helper_invokeStepWithInput($group, ['yo' => 'lo']);

        expect($outputs[0]->get())->toBe(['yo' => 'lo']);
    }
);
