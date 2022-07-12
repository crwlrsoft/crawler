<?php

namespace tests\Steps;

use Crwlr\Crawler\Input;
use Crwlr\Crawler\Output;
use Crwlr\Crawler\Steps\BaseStep;
use Crwlr\Crawler\Steps\Filters\Filter;
use Generator;
use InvalidArgumentException;

use function tests\helper_getStdClassWithData;
use function tests\helper_invokeStepWithInput;

class TestStep extends BaseStep
{
    public ?bool $passesAllFilters = null;

    public function invokeStep(Input $input): Generator
    {
        $this->passesAllFilters = $this->passesAllFilters($input->get());

        yield new Output('yo');
    }
};

test('You can set a filter and passesAllFilters() tells if an output value passes that filter', function () {
    $step = new TestStep();

    $step->where(Filter::equal('hello'));

    helper_invokeStepWithInput($step, new Input('hello'));

    expect($step->passesAllFilters)->toBeTrue();

    helper_invokeStepWithInput($step, new Input('hola'));

    expect($step->passesAllFilters)->toBeFalse();
});

test('You can set multiple filters and passesAllFilters() tells if an output value passes that filters', function () {
    $step = new TestStep();

    $step->where(Filter::stringContains('foo'))
        ->where(Filter::equal('boo foo too'))
        ->where(Filter::notEqual('pew foo tew'));

    helper_invokeStepWithInput($step, new Input('boo foo too'));

    expect($step->passesAllFilters)->toBeTrue();

    helper_invokeStepWithInput($step, new Input('foo something'));

    expect($step->passesAllFilters)->toBeFalse();

    helper_invokeStepWithInput($step, new Input('pew foo tew'));

    expect($step->passesAllFilters)->toBeFalse();
});

test(
    'you can link filters using orWhere and passesAllFilters() is true when one of those filters evaluates to true',
    function () {
        $step = new TestStep();

        $step->where(Filter::stringStartsWith('foo'))
            ->orWhere(Filter::stringStartsWith('bar'))
            ->orWhere(Filter::stringEndsWith('foo'));

        helper_invokeStepWithInput($step, new Input('foo bar baz'));

        expect($step->passesAllFilters)->toBeTrue();

        helper_invokeStepWithInput($step, new Input('bar foo baz'));

        expect($step->passesAllFilters)->toBeTrue();

        helper_invokeStepWithInput($step, new Input('bar baz foo'));

        expect($step->passesAllFilters)->toBeTrue();

        helper_invokeStepWithInput($step, new Input('funky town'));

        expect($step->passesAllFilters)->toBeFalse();
    }
);

it('uses a key from an array when providing a key to the filter() method', function () {
    $step = new TestStep();

    $step->where('vendor', Filter::equal('crwlr'));

    helper_invokeStepWithInput($step, new Input(['vendor' => 'crwlr', 'package' => 'url']));

    expect($step->passesAllFilters)->toBeTrue();

    helper_invokeStepWithInput($step, new Input(['vendor' => 'illuminate', 'package' => 'support']));

    expect($step->passesAllFilters)->toBeFalse();
});

it('uses a key from an object when providing a key to the filter() method', function () {
    $step = new TestStep();

    $step->where('vendor', Filter::equal('crwlr'));

    helper_invokeStepWithInput($step, new Input(
        helper_getStdClassWithData(['vendor' => 'crwlr', 'package' => 'url'])
    ));

    expect($step->passesAllFilters)->toBeTrue();

    helper_invokeStepWithInput($step, new Input(
        helper_getStdClassWithData(['vendor' => 'illuminate', 'package' => 'support'])
    ));

    expect($step->passesAllFilters)->toBeFalse();
});

it('throws an exception when you provide a string as first argument to filter but no second argument', function () {
    $step = new TestStep();

    $step->where('test');
})->throws(InvalidArgumentException::class);
