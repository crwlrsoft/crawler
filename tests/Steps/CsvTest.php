<?php

namespace tests\Steps;

use Crwlr\Crawler\Aggregates\RequestResponseAggregate;
use Crwlr\Crawler\Input;
use Crwlr\Crawler\Steps\Csv;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Utils;
use InvalidArgumentException;
use stdClass;
use function tests\helper_generatorToArray;
use function tests\helper_traverseIterable;

function helper_csvFilePath(string $fileName): string
{
    return __DIR__ . '/_Files/Csv/' . $fileName;
}

it('maps a CSV string', function () {
    $string = <<<CSV
        123,"crwl.io","https://www.crwl.io"
        234,"example.com","https://www.example.com"
        345,"otsch.codes","https://www.otsch.codes"
        456,"crwlr.software","https://www.crwlr.software"
        CSV;

    $results = helper_generatorToArray(Csv::parseString(['id', 'domain', 'url'])->invokeStep(new Input($string)));

    expect($results)->toHaveCount(4);

    expect($results[0]->get())->toBe(['id' => '123', 'domain' => 'crwl.io', 'url' => 'https://www.crwl.io']);

    expect($results[1]->get())->toBe(['id' => '234', 'domain' => 'example.com', 'url' => 'https://www.example.com']);

    expect($results[2]->get())->toBe(['id' => '345', 'domain' => 'otsch.codes', 'url' => 'https://www.otsch.codes']);

    expect($results[3]->get())->toBe(
        ['id' => '456', 'domain' => 'crwlr.software', 'url' => 'https://www.crwlr.software']
    );
});

it('maps a file', function () {
    $results = helper_generatorToArray(
        Csv::parseFile(['id', 'name', 'homepage'])->invokeStep(new Input(helper_csvFilePath('basic.csv')))
    );

    expect($results)->toHaveCount(3);

    expect($results[0]->get())->toBe(['id' => '123', 'name' => 'Otsch', 'homepage' => 'https://www.otsch.codes']);

    expect($results[1]->get())->toBe(['id' => '234', 'name' => 'John Doe', 'homepage' => 'https://www.john.doe']);

    expect($results[2]->get())->toBe(['id' => '345', 'name' => 'Jane Doe', 'homepage' => 'https://www.jane.doe']);
});

it('works with a RequestResponseAggregate as input', function () {
    $body = <<<CSV
        123,"John Doe","+431234567"
        234,"Jane Doe","+432345678"
        CSV;

    $aggregate = new RequestResponseAggregate(new Request('GET', '/'), new Response(200, [], Utils::streamFor($body)));

    $results = helper_generatorToArray(Csv::parseString(['id', 'name', 'phone'])->invokeStep(new Input($aggregate)));

    expect($results)->toHaveCount(2);

    expect($results[0]->get())->toBe(['id' => '123', 'name' => 'John Doe', 'phone' => '+431234567']);

    expect($results[1]->get())->toBe(['id' => '234', 'name' => 'Jane Doe', 'phone' => '+432345678']);
});

it('works with an object having a __toString method', function () {
    $object = new class () {
        public function __toString(): string
        {
            return <<<CSV
                123,"Max Mustermann","+431234567"
                234,"Julia Musterfrau","+432345678"
                CSV;
        }
    };

    $results = helper_generatorToArray(Csv::parseString(['id', 'name', 'phone'])->invokeStep(new Input($object)));

    expect($results)->toHaveCount(2);

    expect($results[0]->get())->toBe(['id' => '123', 'name' => 'Max Mustermann', 'phone' => '+431234567']);

    expect($results[1]->get())->toBe(['id' => '234', 'name' => 'Julia Musterfrau', 'phone' => '+432345678']);
});

it('throws an InvalidArgumentException for other inputs', function (string $method, mixed $input) {
    if ($method === 'string') {
        helper_traverseIterable(
            Csv::parseString(['column'])->invokeStep(new Input($input))
        );
    } elseif ($method === 'file') {
        helper_traverseIterable(
            Csv::parseFile(['column'])->invokeStep(new Input($input))
        );
    }
})->throws(InvalidArgumentException::class)->with([
    ['string', 123],
    ['string', new stdClass()],
    ['string', 12.345],
    ['string', true],
    ['string', null],
    ['file', 123],
    ['file', new stdClass()],
    ['file', 12.345],
    ['file', true],
    ['file', null],
]);

it('can map columns using numerical array keys for the columns', function () {
    $string = <<<CSV
        123,"crwlr.software","https://www.crwlr.software","PHP Web Crawling and Scraping Library"
        234,"otsch.codes","https://www.otsch.codes","I am Otsch, I code"
        CSV;

    $results = helper_generatorToArray(
        Csv::parseString([1 => 'domain', 3 => 'description'])->invokeStep(new Input($string))
    );

    expect($results)->toHaveCount(2);

    expect($results[0]->get())->toBe([
        'domain' => 'crwlr.software', 'description' => 'PHP Web Crawling and Scraping Library'
    ]);

    expect($results[1]->get())->toBe(['domain' => 'otsch.codes', 'description' => 'I am Otsch, I code']);
});

it('can map columns using numerical array keys for the columns when parsing file', function () {
    $results = helper_generatorToArray(
        Csv::parseFile([1 => 'name', 2 => 'homepage'])->invokeStep(new Input(helper_csvFilePath('basic.csv')))
    );

    expect($results)->toHaveCount(3);

    expect($results[0]->get())->toBe(['name' => 'Otsch', 'homepage' => 'https://www.otsch.codes']);

    expect($results[1]->get())->toBe(['name' => 'John Doe', 'homepage' => 'https://www.john.doe']);

    expect($results[2]->get())->toBe(['name' => 'Jane Doe', 'homepage' => 'https://www.jane.doe']);
});

it('can map columns using null for columns to skip', function () {
    $string = <<<CSV
        1997,Ford,E350,"ac, abs, moon",3000.00
        1999,Chevy,"Venture \"Extended Edition\"","",4900.00
        1999,Chevy,"Venture \"Extended Edition, Very Large\"",,5000.00
        CSV;

    $results = helper_generatorToArray(
        Csv::parseString([null, 'make', null, null, 'price'])->invokeStep(new Input($string))
    );

    expect($results)->toHaveCount(3);

    expect($results[0]->get())->toBe(['make' => 'Ford', 'price' => '3000.00']);

    expect($results[1]->get())->toBe(['make' => 'Chevy', 'price' => '4900.00']);

    expect($results[2]->get())->toBe(['make' => 'Chevy', 'price' => '5000.00']);
});

it('can map columns using null for columns to skip when parsing file', function () {
    $results = helper_generatorToArray(
        Csv::parseFile(['id', null, 'homepage'])->invokeStep(new Input(helper_csvFilePath('basic.csv')))
    );

    expect($results)->toHaveCount(3);

    expect($results[0]->get())->toBe(['id' => '123', 'homepage' => 'https://www.otsch.codes']);

    expect($results[1]->get())->toBe(['id' => '234', 'homepage' => 'https://www.john.doe']);

    expect($results[2]->get())->toBe(['id' => '345', 'homepage' => 'https://www.jane.doe']);
});

it('skips the first line when defined via method call to skipFirstLine method', function () {
    $string = <<<CSV
        Year,Make,Model,Description,Price
        1997,Ford,E350,"ac, abs, moon",3000.00
        1999,Chevy,"Venture \"Extended Edition\"","",4900.00
        1999,Chevy,"Venture \"Extended Edition, Very Large\"",,5000.00
        CSV;

    $results = helper_generatorToArray(
        Csv::parseString([null, 'make', null, null, 'price'])
            ->skipFirstLine()
            ->invokeStep(new Input($string))
    );

    expect($results)->toHaveCount(3);

    expect($results[0]->get())->toBe(['make' => 'Ford', 'price' => '3000.00']);
});

it('skips the first line when parsing file when defined via method call to skipFirstLine method', function () {
    $results = helper_generatorToArray(
        Csv::parseFile([1 => 'fach-erste', 2 => 'fach-zweite'])
            ->skipFirstLine()
            ->invokeStep(new Input(helper_csvFilePath('with-column-headlines.csv')))
    );

    expect($results)->toHaveCount(3);

    expect($results[0]->get())->toBe(['fach-erste' => 'Mathematik', 'fach-zweite' => 'Deutsch']);

    expect($results[1]->get())->toBe(['fach-erste' => 'Sport', 'fach-zweite' => 'Deutsch']);

    expect($results[2]->get())->toBe(['fach-erste' => 'Sport', 'fach-zweite' => 'Religion (ev., kath.)']);
});

it('skips the first line when defined via constructor param', function () {
    $string = <<<CSV
        Year,Make,Model,Description,Price
        1997,Ford,E350,"ac, abs, moon",3000.00
        CSV;

    $results = helper_generatorToArray(
        Csv::parseString([null, 'make', null, null, 'price'], true)
            ->invokeStep(new Input($string))
    );

    expect($results)->toHaveCount(1);

    expect($results[0]->get())->toBe(['make' => 'Ford', 'price' => '3000.00']);
});

it('skips the first line when parsing file when defined via constructor param', function () {
    $results = helper_generatorToArray(
        Csv::parseFile([1 => 'fach-erste', 3 => 'fach-dritte'], true)
            ->invokeStep(new Input(helper_csvFilePath('with-column-headlines.csv')))
    );

    expect($results)->toHaveCount(3);

    expect($results[0]->get())->toBe(['fach-erste' => 'Mathematik', 'fach-dritte' => 'Englisch']);

    expect($results[1]->get())->toBe(['fach-erste' => 'Sport', 'fach-dritte' => 'Englisch']);

    expect($results[2]->get())->toBe(['fach-erste' => 'Sport', 'fach-dritte' => 'Kunst']);
});

// TODO: tests for separator, enclosure, escape
