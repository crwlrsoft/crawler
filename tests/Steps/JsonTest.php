<?php

namespace tests\Steps;

use Crwlr\Crawler\Loader\Http\Messages\RespondedRequest;
use Crwlr\Crawler\Steps\Json;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Utils;

use function tests\helper_invokeStepWithInput;

it('accepts RespondedRequest as input', function () {
    $json = '{ "data": { "foo": "bar" } }';

    $respondedRequest = new RespondedRequest(new Request('GET', '/'), new Response(body: Utils::streamFor($json)));

    $output = helper_invokeStepWithInput(Json::get(['foo' => 'data.foo']), $respondedRequest);

    expect($output)->toHaveCount(1);

    expect($output[0]->get())->toBe(['foo' => 'bar']);
});

it('accepts PSR-7 Response as input', function () {
    $json = '{ "data": { "foo": "bar" } }';

    $response = new Response(body: Utils::streamFor($json));

    $output = helper_invokeStepWithInput(Json::get(['foo' => 'data.foo']), $response);

    expect($output)->toHaveCount(1);

    expect($output[0]->get())->toBe(['foo' => 'bar']);
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

    expect($output)->toHaveCount(1);

    expect($output[0]->get())->toBe(['foo' => 'bar', 'baz' => 'yo']);
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

    expect($output)->toHaveCount(3);

    expect($output[0]->get())->toBe(['name' => 'Peter', 'age' => 19]);

    expect($output[1]->get())->toBe(['name' => 'Paul', 'age' => 22]);

    expect($output[2]->get())->toBe(['name' => 'Mary', 'age' => 20]);
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

    expect($output)->toHaveCount(4);

    expect($output[0]->get())->toBe(['nickname' => 'Axel']);

    expect($output[1]->get())->toBe(['nickname' => 'Lilo']);

    expect($output[2]->get())->toBe(['nickname' => 'Poppi']);

    expect($output[3]->get())->toBe(['nickname' => 'Dominik']);
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

    expect($outputs)->toHaveCount(1);

    expect($outputs[0]->get())->toBe(['foo' => 'one', 'bar' => 'two', 'baz' => 'three']);
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

    expect($outputs)->toHaveCount(1);

    expect($outputs[0]->get())->toBe([
        'foo' => 'https://www.example.com',
        'bar' => 2,
        'baz' => 'some: thing',
    ]);
});
