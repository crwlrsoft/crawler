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

    $step->filter(Filter::equal('hello'));

    helper_invokeStepWithInput($step, new Input('hello'));

    expect($step->passesAllFilters)->toBeTrue();

    helper_invokeStepWithInput($step, new Input('hola'));

    expect($step->passesAllFilters)->toBeFalse();
});

test('You can set multiple filters and passesAllFilters() tells if an output value passes that filters', function () {
    $step = new TestStep();

    $step->filter(Filter::stringContains('foo'))
        ->filter(Filter::equal('boo foo too'))
        ->filter(Filter::notEqual('pew foo tew'));

    helper_invokeStepWithInput($step, new Input('boo foo too'));

    expect($step->passesAllFilters)->toBeTrue();

    helper_invokeStepWithInput($step, new Input('foo something'));

    expect($step->passesAllFilters)->toBeFalse();

    helper_invokeStepWithInput($step, new Input('pew foo tew'));

    expect($step->passesAllFilters)->toBeFalse();
});

it('uses a key from an array when providing a key to the filter() method', function () {
    $step = new TestStep();

    $step->filter('vendor', Filter::equal('crwlr'));

    helper_invokeStepWithInput($step, new Input(['vendor' => 'crwlr', 'package' => 'url']));

    expect($step->passesAllFilters)->toBeTrue();

    helper_invokeStepWithInput($step, new Input(['vendor' => 'illuminate', 'package' => 'support']));

    expect($step->passesAllFilters)->toBeFalse();
});

it('uses a key from an object when providing a key to the filter() method', function () {
    $step = new TestStep();

    $step->filter('vendor', Filter::equal('crwlr'));

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

    $step->filter('test');
})->throws(InvalidArgumentException::class);
