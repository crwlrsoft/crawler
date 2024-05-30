<?php

namespace tests\Steps;

use Crwlr\Crawler\Crawler;
use Crwlr\Crawler\HttpCrawler;
use Crwlr\Crawler\Input;
use Crwlr\Crawler\Loader\Http\Messages\RespondedRequest;
use Crwlr\Crawler\Logger\CliLogger;
use Crwlr\Crawler\Output;
use Crwlr\Crawler\Steps\BaseStep;
use Crwlr\Crawler\Steps\Exceptions\PreRunValidationException;
use Crwlr\Crawler\Steps\Filters\Filter;
use Crwlr\Crawler\Steps\Html;
use Crwlr\Crawler\Steps\Loading\Http;
use Crwlr\Crawler\Steps\Step;
use Crwlr\Crawler\Steps\StepOutputType;
use Generator;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use InvalidArgumentException;

use PHPUnit\Framework\TestCase;

use function tests\helper_getInputReturningStep;
use function tests\helper_getStdClassWithData;
use function tests\helper_getStepFilesContent;
use function tests\helper_getValueReturningStep;
use function tests\helper_invokeStepWithInput;

class TestStep extends BaseStep
{
    public ?bool $passesAllFilters = null;

    public function invokeStep(Input $input): Generator
    {
        $this->passesAllFilters = $this->passesAllFilters($input->get());

        yield new Output('yo');
    }
}

/** @var TestCase $this */

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
    },
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
        helper_getStdClassWithData(['vendor' => 'crwlr', 'package' => 'url']),
    ));

    expect($step->passesAllFilters)->toBeTrue();

    helper_invokeStepWithInput($step, new Input(
        helper_getStdClassWithData(['vendor' => 'illuminate', 'package' => 'support']),
    ));

    expect($step->passesAllFilters)->toBeFalse();
});

it('filters using a custom Closure filter', function () {
    $step = new TestStep();

    $step->where('bar', Filter::custom(function (mixed $value) {
        return in_array($value, ['one', 'two', 'three'], true);
    }));

    helper_invokeStepWithInput($step, ['foo' => 'one', 'bar' => 'two']);

    expect($step->passesAllFilters)->toBeTrue();

    helper_invokeStepWithInput($step, ['foo' => 'three', 'bar' => 'four']);

    expect($step->passesAllFilters)->toBeFalse();
});

it('throws an exception when you provide a string as first argument to filter but no second argument', function () {
    $step = new TestStep();

    $step->where('test');
})->throws(InvalidArgumentException::class);

it('removes an UTF-8 byte order mark from the beginning of a string', function () {
    $step = new class () extends Step {
        protected function invoke(mixed $input): Generator
        {
            yield $input;
        }

        protected function validateAndSanitizeInput(mixed $input): mixed
        {
            return parent::validateAndSanitizeStringOrHttpResponse($input);
        }
    };

    $stringWithBom = helper_getStepFilesContent('Xml/rss-with-bom.xml');

    $response = new RespondedRequest(
        new Request('GET', 'https://www.example.com/rss'),
        new Response(body: $stringWithBom),
    );

    $outputs = helper_invokeStepWithInput($step, $response);

    expect($outputs)->toHaveCount(1);

    expect($outputs[0]->get())->toBeString();

    expect(substr($outputs[0]->get(), 0, 5))->toBe('<?xml');

    // Also test with string as input.
    $outputs = helper_invokeStepWithInput($step, $stringWithBom);

    expect($outputs)->toHaveCount(1);

    expect($outputs[0]->get())->toBeString();

    expect(substr($outputs[0]->get(), 0, 5))->toBe('<?xml');
});

/* ----------------------------- validateBeforeRun() ----------------------------- */

it(
    'throws an exception in validateBeforeRun() when output type is scalar and keep() was used but not keepAs()',
    function () {
        $step = new class () extends Step {
            protected function invoke(mixed $input): Generator
            {
                yield $input;
            }

            public function outputType(): StepOutputType
            {
                return StepOutputType::Scalar;
            }
        };

        $step->keep()->validateBeforeRun(Http::get());
    },
)->throws(PreRunValidationException::class);

it(
    'logs a warning in validateBeforeRun() when output type is mixed and keep() was used but not keepAs()',
    function () {
        class SomeDemoStep extends Step
        {
            protected function invoke(mixed $input): Generator
            {
                yield $input;
            }
        }

        $step = new SomeDemoStep();

        $step->addLogger(new CliLogger())->keep()->validateBeforeRun(Http::get());

        expect($this->getActualOutputForAssertion())
            ->toContain('The tests\Steps\SomeDemoStep step potentially yields scalar value outputs');
    },
);

it('does not throw an exception or log a warning when output type is scalar and keepAs() was called', function () {
    helper_getInputReturningStep()->addLogger(new CliLogger())->keepAs('foo')->validateBeforeRun(Http::get());

    expect($this->getActualOutputForAssertion())
        ->not()
        ->toContain('The tests\Steps\SomeDemoStep step potentially yields scalar value outputs');
});

it('does not throw an exception or log a warning when output type is scalar and outputKey() was called', function () {
    helper_getInputReturningStep()->addLogger(new CliLogger())->outputKey('foo')->validateBeforeRun(Http::get());

    expect($this->getActualOutputForAssertion())
        ->not()
        ->toContain('The tests\Steps\SomeDemoStep step potentially yields scalar value outputs');
});

it('throws an exception when keepFromInput() was called and initial inputs contain a scalar value', function () {
    Http::get()
        ->keepFromInput()
        ->validateBeforeRun([
            ['foo' => 'bar', 'baz' => 'quz'],
            'scalar',
        ]);
})->throws(PreRunValidationException::class);

it('does not throw an exception when keepFromInput() was called and initial inputs are associative array', function () {
    Http::get()
        ->keepFromInput()
        ->validateBeforeRun([
            ['foo' => 'one'],
            ['foo' => 'two'],
        ]);
})->throwsNoExceptions();

it('logs an error when initial inputs are empty', function () {
    Http::get()
        ->addLogger(new CliLogger())
        ->validateBeforeRun([]);

    expect($this->getActualOutputForAssertion())
        ->toContain('You did not provide any initial inputs for your crawler.');
});

it('throws an exception when keepFromInput() was called and previous step yields scalar outputs', function () {
    Http::get()
        ->keepFromInput()
        ->validateBeforeRun(Html::getLink('.link'));
})->throws(PreRunValidationException::class);

it('does not throw an exception when keepInputAs() was called and previous step yields scalar outputs', function () {
    Http::get()
        ->keepInputAs('link')
        ->validateBeforeRun(Html::getLink('.link'));
})->throwsNoExceptions();

it('logs a warning, when keepFromInput() was called and previous step yields mixed outputs', function () {
    $stepWithMixedOutputType = new class () extends Step {
        protected function invoke(mixed $input): Generator
        {
            yield 'yo';
        }

        public function outputType(): StepOutputType
        {
            return StepOutputType::Mixed;
        }
    };

    Http::get()
        ->keepFromInput()
        ->addLogger(new CliLogger())
        ->validateBeforeRun($stepWithMixedOutputType);

    expect($this->getActualOutputForAssertion())
        ->toContain('potentially yields scalar value outputs ')
        ->toContain('the next step can not keep it by using keepFromInput()');
});

/* ----------------------------- keep() ----------------------------- */

it('adds all from array output to the keep array in the output object, when keep() is called', function () {
    $step = helper_getInputReturningStep()->keep();

    $outputs = helper_invokeStepWithInput($step, ['foo' => 'one', 'bar' => 'two']);

    expect($outputs[0]->keep)->toBe(['foo' => 'one', 'bar' => 'two']);
});

it('adds all from object output to the keep array in the output object, when keep() is called', function () {
    $step = helper_getInputReturningStep()->keep();

    $outputObject = new class () {
        /**
         * @return array<string, string>
         */
        public function toArray(): array
        {
            return ['key' => 'value', 'key2' => 'value2'];
        }
    };

    $outputs = helper_invokeStepWithInput($step, $outputObject);

    expect($outputs[0]->keep)->toBe(['key' => 'value', 'key2' => 'value2']);
});

it('adds a key from array output to the keep array in the output, when keep() was called with a string', function () {
    $step = helper_getInputReturningStep()->keep('bar');

    $outputs = helper_invokeStepWithInput($step, ['foo' => 'one', 'bar' => 'two']);

    expect($outputs[0]->keep)->toBe(['bar' => 'two']);
});

it('adds multiple keys to the keep array in the output, when keep() was called with an array', function () {
    $step = helper_getInputReturningStep()->keep(['foo', 'baz']);

    $outputs = helper_invokeStepWithInput($step, ['foo' => 'one', 'bar' => 'two', 'baz' => 'three']);

    expect($outputs[0]->keep)->toBe(['foo' => 'one', 'baz' => 'three']);
});

it('maps output data to the keep array in the output, when keep() was called with an associative array', function () {
    $step = helper_getInputReturningStep()->keep(['foo', 'mappedKey' => 'baz']);

    $outputs = helper_invokeStepWithInput($step, ['foo' => 'one', 'bar' => 'two', 'baz' => 'three']);

    expect($outputs[0]->keep)->toBe(['foo' => 'one', 'mappedKey' => 'three']);
});

it('logs an error when output is scalar value and keep was used, and adds the value with an unnamed key', function () {
    $step = helper_getInputReturningStep()
        ->addLogger(new CliLogger())
        ->keep();

    $outputs = helper_invokeStepWithInput($step, 'hello');

    expect($outputs[0]->keep)->toBe(['unnamed1' => 'hello'])
        ->and($this->getActualOutputForAssertion())
        ->toContain('yielded an output that is neither an associative array, nor an object');
});

it('repeatedly adds properties with unnamed keys with increasing numbers', function () {
    $step = helper_getValueReturningStep('world')
        ->keepFromInput()
        ->keep();

    $outputs = helper_invokeStepWithInput($step, new Input('hello', keep: ['unnamed1' => 'servus']));

    expect($outputs)->toHaveCount(1)
        ->and($outputs[0]->keep)->toBe(['unnamed1' => 'servus', 'unnamed2' => 'hello', 'unnamed3' => 'world']);
});

/* ----------------------------- keepAs() ----------------------------- */

it('adds scalar value output with the defined key to keep output data, when keepAs() was used', function () {
    $step = helper_getInputReturningStep()
        ->keepAs('greeting');

    $outputs = helper_invokeStepWithInput($step, 'hello');

    expect($outputs[0]->keep)->toBe(['greeting' => 'hello']);
});

it('adds array output with the defined key to keep output data, when keepAs() was used', function () {
    $step = helper_getInputReturningStep()
        ->keepAs('test');

    $outputs = helper_invokeStepWithInput($step, ['foo' => 'bar']);

    expect($outputs[0]->keep)->toBe(['test' => ['foo' => 'bar']]);
});

/* ----------------------------- keepFromInput() ----------------------------- */

it('adds all from array input to the keep array in the output object, when keepFromInput() is called', function () {
    $step = helper_getValueReturningStep('foo')->keepFromInput();

    $outputs = helper_invokeStepWithInput($step, ['foo' => 'one', 'bar' => 'two']);

    expect($outputs[0]->keep)->toBe(['foo' => 'one', 'bar' => 'two']);
});

it('adds all from object input to the keep array in the output object, when keepFromInput() is called', function () {
    $step = helper_getValueReturningStep('foo')->keepFromInput();

    $inputObject = new class () {
        /**
         * @return array<string, string>
         */
        public function toArray(): array
        {
            return ['key' => 'value', 'key2' => 'value2'];
        }
    };

    $outputs = helper_invokeStepWithInput($step, $inputObject);

    expect($outputs[0]->keep)->toBe(['key' => 'value', 'key2' => 'value2']);
});

it(
    'adds a key from array input to the keep array in the output, when keepFromInput() was called with a string',
    function () {
        $step = helper_getValueReturningStep('foo')->keepFromInput('bar');

        $outputs = helper_invokeStepWithInput($step, ['foo' => 'one', 'bar' => 'two']);

        expect($outputs[0]->keep)->toBe(['bar' => 'two']);
    },
);

it(
    'adds multiple keys from the input to the keep array in the output, when keepFromInput() was called with an array',
    function () {
        $step = helper_getValueReturningStep('foo')->keepFromInput(['foo', 'baz']);

        $outputs = helper_invokeStepWithInput($step, ['foo' => 'one', 'bar' => 'two', 'baz' => 'three']);

        expect($outputs[0]->keep)->toBe(['foo' => 'one', 'baz' => 'three']);
    },
);

it(
    'maps input data to the keep array in the output, when keepFromInput() was called with an associative array',
    function () {
        $step = helper_getValueReturningStep('foo')->keepFromInput(['foo', 'mappedKey' => 'baz']);

        $outputs = helper_invokeStepWithInput($step, ['foo' => 'one', 'bar' => 'two', 'baz' => 'three']);

        expect($outputs[0]->keep)->toBe(['foo' => 'one', 'mappedKey' => 'three']);
    },
);

it('logs an error when input is scalar value and keep was used, and adds the value with an unnamed key', function () {
    $step = helper_getValueReturningStep('foo')
        ->addLogger(new CliLogger())
        ->keepFromInput();

    $outputs = helper_invokeStepWithInput($step, 'hey');

    expect($outputs[0]->keep)->toBe(['unnamed1' => 'hey'])
        ->and($this->getActualOutputForAssertion())
        ->toContain('received an input that is neither an associative array, nor an object');
});

/* ----------------------------- keepInputAs() ----------------------------- */

it('adds scalar value input with the defined key to keep output data, when keepInputAs() was used', function () {
    $step = helper_getValueReturningStep('yo')
        ->keepInputAs('greeting');

    $outputs = helper_invokeStepWithInput($step, 'hello');

    expect($outputs[0]->keep)->toBe(['greeting' => 'hello']);
});

it('adds array input with the defined key to keep output data, when keepAs() was used', function () {
    $step = helper_getValueReturningStep('yay')
        ->keepInputAs('test');

    $outputs = helper_invokeStepWithInput($step, ['foo' => 'bar']);

    expect($outputs[0]->keep)->toBe(['test' => ['foo' => 'bar']]);
});

/* ------------------------ combinations of keep calls ------------------------ */

it('makes an array of values when the same key should be kept from input and output', function () {
    $step = helper_getValueReturningStep(['foo' => 'one', 'bar' => 'two'])
        ->keepFromInput('foo')
        ->keep(['foo', 'bar']);

    $outputs = helper_invokeStepWithInput($step, ['foo' => 'bar']);

    expect($outputs[0]->keep)->toBe(['foo' => ['bar', 'one'], 'bar' => 'two']);
});

test('same key in input and output, but they are mapped to different keys for keep data', function () {
    $step = helper_getValueReturningStep(['foo' => 'one', 'bar' => 'two'])
        ->keepFromInput(['inputFoo' => 'foo'])
        ->keep(['foo', 'bar']);

    $outputs = helper_invokeStepWithInput($step, ['foo' => 'bar']);

    expect($outputs[0]->keep)->toBe(['inputFoo' => 'bar', 'foo' => 'one', 'bar' => 'two']);
});

it('merges data for the same key recursively', function () {
    $step = helper_getValueReturningStep(['foo' => ['one', 'two'], 'bar' => 'two'])
        ->keepFromInput('foo')
        ->keep(['foo', 'bar']);

    $outputs = helper_invokeStepWithInput(
        $step,
        new Input(['foo' => ['bar', 'baz']], keep: ['foo' => 'test']),
    );

    expect($outputs[0]->keep)->toBe(['foo' => ['test', 'bar', 'baz', 'one', 'two'], 'bar' => 'two']);
});

/* ----------------------------- keepsAnything() ----------------------------- */

test(
    'keepsAnything() returns true when one of keep(), keepAs(), keepFromInput() or keepInputAs() was called',
    function (bool $callKeep, bool $callKeepAs, bool $callKeepFromInput, bool $callKeepInputAs, bool $expected) {
        $step = helper_getInputReturningStep();

        if ($callKeep) {
            $step->keep();
        }

        if ($callKeepAs) {
            $step->keepAs('foo');
        }

        if ($callKeepFromInput) {
            $step->keepFromInput();
        }

        if ($callKeepInputAs) {
            $step->keepInputAs('bar');
        }

        expect($step->keepsAnything())->toBe($expected);
    },
)->with([
    [false, false, false, false, false],
    [true, false, false, false, true],
    [false, true, false, false, true],
    [false, false, true, false, true],
    [false, false, false, true, true],
]);

test(
    'keepsAnythingFromInputData() returns true when one of keepFromInput() or keepInputAs() was called',
    function (bool $callKeep, bool $callKeepAs, bool $callKeepFromInput, bool $callKeepInputAs, bool $expected) {
        $step = helper_getInputReturningStep();

        if ($callKeep) {
            $step->keep();
        }

        if ($callKeepAs) {
            $step->keepAs('foo');
        }

        if ($callKeepFromInput) {
            $step->keepFromInput();
        }

        if ($callKeepInputAs) {
            $step->keepInputAs('bar');
        }

        expect($step->keepsAnythingFromInputData())->toBe($expected);
    },
)->with([
    [false, false, false, false, false],
    [true, false, false, false, false],
    [false, true, false, false, false],
    [false, false, true, false, true],
    [false, false, false, true, true],
]);

test(
    'keepsAnythingFromOutputData() returns true when one of keep() or keepAs() was called',
    function (bool $callKeep, bool $callKeepAs, bool $callKeepFromInput, bool $callKeepInputAs, bool $expected) {
        $step = helper_getInputReturningStep();

        if ($callKeep) {
            $step->keep();
        }

        if ($callKeepAs) {
            $step->keepAs('foo');
        }

        if ($callKeepFromInput) {
            $step->keepFromInput();
        }

        if ($callKeepInputAs) {
            $step->keepInputAs('bar');
        }

        expect($step->keepsAnythingFromOutputData())->toBe($expected);
    },
)->with([
    [false, false, false, false, false],
    [true, false, false, false, true],
    [false, true, false, false, true],
    [false, false, true, false, false],
    [false, false, false, true, false],
]);

/* ----------------------------- sub crawlers ----------------------------- */

it('logs an error message when a sub crawler is defined and step has no reference to a parent crawler', function () {
    $step = helper_getInputReturningStep()->addLogger(new CliLogger());

    $step->subCrawlerFor('bar', function (Crawler $crawler) {
        return $crawler->addStep(Http::get());
    });

    helper_invokeStepWithInput($step, ['foo' => 'one', 'bar' => ['https://www.example.com']]);

    expect($this->getActualOutputForAssertion())->toContain(
        'Can\'t make sub crawler, because the step has no reference to the parent crawler.',
    );
});

it('logs an error message when a sub crawler is defined and output is scalar value', function () {
    $step = helper_getInputReturningStep()->addLogger(new CliLogger());

    $step->setParentCrawler(HttpCrawler::make()->withUserAgent('Test'));

    $step->subCrawlerFor('bar', function (Crawler $crawler) {
        return $crawler->addStep(Http::get());
    });

    helper_invokeStepWithInput($step, 'foo');

    expect($this->getActualOutputForAssertion())
        ->toContain('The sub crawler feature works only with outputs that are associative arrays');
});

it('runs a sub crawler for a certain output property', function () {
    $step = helper_getInputReturningStep()->addLogger(new CliLogger());

    $step->setParentCrawler(HttpCrawler::make()->withUserAgent('Test'));

    $step->subCrawlerFor('bar', function (Crawler $crawler) {
        return $crawler->addStep(Html::root()->extract(['title' => 'h1']));
    });

    $results = helper_invokeStepWithInput($step, [
        'foo' => 'hey',
        'bar' => '<html><head></head><body><h1>Hello World!</h1></body>',
    ]);

    expect($results)->toHaveCount(1)
        ->and($results[0]->get())->toBe(['foo' => 'hey', 'bar' => ['title' => 'Hello World!']]);
});

test('when a sub crawler returns multiple results, they are an array in the parent output', function () {
    $step = helper_getInputReturningStep()->addLogger(new CliLogger());

    $step->setParentCrawler(HttpCrawler::make()->withUserAgent('Test'));

    $step->subCrawlerFor('bar', function (Crawler $crawler) {
        return $crawler->addStep(Html::each('.item')->extract(['title' => 'h3']));
    });

    $html = <<<HTML
<html>
<head></head>
<body>
<div class="item"><h3>one</h3></div>
<div class="item"><h3>two</h3></div>
<div class="item"><h3>three</h3></div>
</body>
HTML;

    $results = helper_invokeStepWithInput($step, ['foo' => 'hey', 'bar' => $html, 'baz' => 'yo']);

    expect($results)->toHaveCount(1)
        ->and($results[0]->get())
        ->toBe([
            'foo' => 'hey',
            'bar' => [
                ['title' => 'one'],
                ['title' => 'two'],
                ['title' => 'three'],
            ],
            'baz' => 'yo',
        ]);
});

it('runs a sub crawler with multiple inputs, when defined property is array', function () {
    $step = helper_getInputReturningStep()->addLogger(new CliLogger());

    $step->setParentCrawler(HttpCrawler::make()->withUserAgent('Test'));

    $step->subCrawlerFor('bar', function (Crawler $crawler) {
        return $crawler->addStep(Html::root()->extract(['title' => 'h1']));
    });

    $results = helper_invokeStepWithInput($step, [
        'foo' => 'hey',
        'bar' => [
            '<html><head></head><body><h1>No. 1</h1></body>',
            '<html><head></head><body><h1>No. 2</h1></body>',
            '<html><head></head><body><h1>No. 3</h1></body>',
        ],
        'baz' => 'yo',
    ]);

    expect($results)->toHaveCount(1)
        ->and($results[0]->get())
        ->toBe([
            'foo' => 'hey',
            'bar' => [
                ['title' => 'No. 1'],
                ['title' => 'No. 2'],
                ['title' => 'No. 3'],
            ],
            'baz' => 'yo',
        ]);
});

it('does not run a sub crawler, when output does not contain the defined key', function () {
    $step = helper_getInputReturningStep()->addLogger(new CliLogger());

    $step->setParentCrawler(HttpCrawler::make()->withUserAgent('Test'));

    $step->subCrawlerFor('bar', function (Crawler $crawler) {
        return $crawler->addStep(Html::root()->extract(['title' => 'h1']));
    });

    $results = helper_invokeStepWithInput($step, ['foo' => 'hey', 'baz' => 'ho']);

    expect($results)->toHaveCount(1)
        ->and($results[0]->get())->toBe(['foo' => 'hey', 'baz' => 'ho']);
});
