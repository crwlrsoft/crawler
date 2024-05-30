<?php

namespace tests\Steps;

use Crwlr\Crawler\Loader\Http\Messages\RespondedRequest;
use Crwlr\Crawler\Logger\CliLogger;
use Crwlr\Crawler\Steps\Json;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Utils;
use PHPUnit\Framework\TestCase;

use function tests\helper_invokeStepWithInput;

/** @var TestCase $this */

it('accepts RespondedRequest as input', function () {
    $json = '{ "data": { "foo": "bar" } }';

    $respondedRequest = new RespondedRequest(new Request('GET', '/'), new Response(body: Utils::streamFor($json)));

    $output = helper_invokeStepWithInput(Json::get(['foo' => 'data.foo']), $respondedRequest);

    expect($output)->toHaveCount(1)
        ->and($output[0]->get())->toBe(['foo' => 'bar']);
});

it('accepts PSR-7 Response as input', function () {
    $json = '{ "data": { "foo": "bar" } }';

    $response = new Response(body: Utils::streamFor($json));

    $output = helper_invokeStepWithInput(Json::get(['foo' => 'data.foo']), $response);

    expect($output)->toHaveCount(1)
        ->and($output[0]->get())->toBe(['foo' => 'bar']);
});

it('extracts data defined using dot notation', function () {
    $json = <<<JSON
        {
            "data": {
                "target": {
                    "foo": "bar",
                    "bar": "foo",
                    "baz": "yo"
                }
            }
        }
        JSON;

    $output = helper_invokeStepWithInput(Json::get(['foo' => 'data.target.foo', 'baz' => 'data.target.baz']), $json);

    expect($output)->toHaveCount(1)
        ->and($output[0]->get())->toBe(['foo' => 'bar', 'baz' => 'yo']);
});

it('uses the array values in the mapping as output key when no string keys defined in the mapping array', function () {
    $jsonString = <<<JSON
        {
            "data": {
                "target": {
                    "foo": "bar",
                    "bar": "foo",
                    "baz": "yo"
                }
            }
        }
        JSON;

    $output = helper_invokeStepWithInput(Json::get(['data.target.foo', 'baz' => 'data.target.baz']), $jsonString);

    expect($output[0]->get())->toBe(['data.target.foo' => 'bar', 'baz' => 'yo']);
});

it('can get items from a json array using a numeric key', function () {
    $jsonString = <<<JSON
        {
            "data": {
                "target": {
                    "array": [
                        { "name": "Adam" },
                        { "name": "Eve" }
                    ]
                }
            }
        }
        JSON;

    $output = helper_invokeStepWithInput(Json::get(['name' => 'data.target.array.1.name']), $jsonString);

    expect($output[0]->get())->toBe(['name' => 'Eve']);
});

test('Using the each method you can iterate over a json array and yield multiple results', function () {
    $json = <<<JSON
        {
            "list": {
                "people": [
                    { "name": "Peter", "age": { "years": 19 } },
                    { "name": "Paul", "age": { "years": 22 } },
                    { "name": "Mary", "age": { "years": 20 } }
                ]
            }
        }
        JSON;

    $output = helper_invokeStepWithInput(Json::each('list.people', ['name' => 'name', 'age' => 'age.years']), $json);

    expect($output)->toHaveCount(3)
        ->and($output[0]->get())->toBe(['name' => 'Peter', 'age' => 19])
        ->and($output[1]->get())->toBe(['name' => 'Paul', 'age' => 22])
        ->and($output[2]->get())->toBe(['name' => 'Mary', 'age' => 20]);
});

test('When the root element is an array you can use each with empty string as param', function () {
    $jsonString = <<<JSON
        [
            { "firstname": "Axel", "surname": "Klingmeier", "nickname": "Axel" },
            { "firstname": "Lieselotte", "surname": "Schroll", "nickname": "Lilo" },
            { "firstname": "Paula", "surname": "Monowitsch", "nickname": "Poppi" },
            { "firstname": "Dominik", "surname": "Kascha", "nickname": "Dominik" }
        ]
        JSON;

    $output = helper_invokeStepWithInput(Json::each('', ['nickname']), $jsonString);

    expect($output)->toHaveCount(4)
        ->and($output[0]->get())->toBe(['nickname' => 'Axel'])
        ->and($output[1]->get())->toBe(['nickname' => 'Lilo'])
        ->and($output[2]->get())->toBe(['nickname' => 'Poppi'])
        ->and($output[3]->get())->toBe(['nickname' => 'Dominik']);

});

it('yields no results and logs a warning when the target for "each" does not exist', function () {
    $jsonString = '{ "foo": { "bar": [{ "number": "one" }, { "number": "two" }] } }';

    $step = Json::each('boo.bar', ['number']);

    $step->addLogger(new CliLogger());

    $output = helper_invokeStepWithInput($step, $jsonString);

    expect($output)->toHaveCount(0);

    $logOutput = $this->getActualOutputForAssertion();

    expect($logOutput)->toContain('The target of "each" does not exist in the JSON data.');
});

it('also works with JS style JSON objects without quotes around keys', function () {
    $jsonString = <<<JSON
        {
            foo: "one",
            bar: "two",
            "baz": "three"
        }
        JSON;

    $outputs = helper_invokeStepWithInput(Json::get(['foo', 'bar', 'baz']), $jsonString);

    expect($outputs)->toHaveCount(1)
        ->and($outputs[0]->get())->toBe(['foo' => 'one', 'bar' => 'two', 'baz' => 'three']);
});

it('also correctly fixes keys without quotes, even when values contain colons', function () {
    $jsonString = <<<JSON
        {
            foo: "https://www.example.com",
            bar: 2,
            "baz": "some: thing"
        }
        JSON;

    $outputs = helper_invokeStepWithInput(Json::get(['foo', 'bar', 'baz']), $jsonString);

    expect($outputs)->toHaveCount(1)
        ->and($outputs[0]->get())
        ->toBe([
            'foo' => 'https://www.example.com',
            'bar' => 2,
            'baz' => 'some: thing',
        ]);
});

it('also correctly fixes keys without quotes, when the value is an empty string', function () {
    $jsonString = <<<JSON
        {
            foo: "",
            "bar": "baz"
        }
        JSON;

    $outputs = helper_invokeStepWithInput(Json::get(['foo', 'bar']), $jsonString);

    expect($outputs)->toHaveCount(1)
        ->and($outputs[0]->get())
        ->toBe([
            'foo' => '',
            'bar' => 'baz',
        ]);
});

it('works with a string that is an HTML document and inside the body there\'s a JSON object', function () {
    $jsonString = <<<HTML
        <!doctype html>
        <html lang="en">
        <head>
        <title>JSON</title>
        </head>
        <body>
        { "foo": "Hello World!", "bar": "baz" }
        </body>
        HTML;

    $outputs = helper_invokeStepWithInput(Json::get(['title' => 'foo']), $jsonString);

    expect($outputs)->toHaveCount(1)
        ->and($outputs[0]->get())
        ->toBe(['title' => 'Hello World!']);
});

it('gets the whole JSON object as array, when using the all() method', function () {
    $jsonString = <<<JSON
        {
            "foo": "one",
            "bar": "two",
            "array": ["one", "two", "three"]
        }
        JSON;

    $outputs = helper_invokeStepWithInput(Json::all(), $jsonString);

    expect($outputs)->toHaveCount(1)
        ->and($outputs[0]->get())
        ->toBe([
            'foo' => 'one',
            'bar' => 'two',
            'array' => ['one', 'two', 'three'],
        ]);
});

it('can also map the whole decoded data array to a output property', function () {
    $jsonString = <<<JSON
        {
            "foo": "one",
            "bar": "two",
            "array": ["one", "two", "three"]
        }
        JSON;

    $outputs = helper_invokeStepWithInput(Json::get(['all' => '*']), $jsonString);

    expect($outputs)
        ->toHaveCount(1)
        ->and($outputs[0]->get())
        ->toBe([
            'all' => [
                'foo' => 'one',
                'bar' => 'two',
                'array' => ['one', 'two', 'three'],
            ],
        ]);
});

test('when there is a key * in the object, the * gets that key, not the whole decoded data', function () {
    $jsonString = <<<JSON
        {
            "*": "yes",
            "foo": "bar",
            "baz": "quz"
        }
        JSON;

    $outputs = helper_invokeStepWithInput(Json::get(['shouldBeYes' => '*']), $jsonString);

    expect($outputs)
        ->toHaveCount(1)
        ->and($outputs[0]->get())
        ->toBe(['shouldBeYes' => 'yes']);
});

it('can also get the whole decoded data in the each() context', function () {
    $jsonString = <<<JSON
        [
            { "name": "foo", "value": "one" },
            { "name": "bar", "value": "two" },
            { "name": "baz", "value": "three" }
        ]
        JSON;

    $outputs = helper_invokeStepWithInput(Json::each('', ['full' => '*']), $jsonString);

    expect($outputs)
        ->toHaveCount(3)
        ->and($outputs[0]->get())
        ->toBe(['full' => ['name' => 'foo', 'value' => 'one']])
        ->and($outputs[1]->get())
        ->toBe(['full' => ['name' => 'bar', 'value' => 'two']])
        ->and($outputs[2]->get())
        ->toBe(['full' => ['name' => 'baz', 'value' => 'three']]);
});

test('in the each() context, when there is a key *, it gets that, not the whole decoded data', function () {
    $jsonString = <<<JSON
        [
            { "name": "foo", "value": "one", "*": "yo" },
            { "name": "bar", "value": "two" }
        ]
        JSON;

    $outputs = helper_invokeStepWithInput(Json::each('', ['full' => '*']), $jsonString);

    expect($outputs)
        ->toHaveCount(2)
        ->and($outputs[0]->get())
        ->toBe(['full' => 'yo'])
        ->and($outputs[1]->get())
        ->toBe(['full' => ['name' => 'bar', 'value' => 'two']]);
});
