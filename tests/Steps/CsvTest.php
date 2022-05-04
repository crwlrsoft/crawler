<?php

namespace tests\Steps;

use Crwlr\Crawler\Loader\Http\Messages\RespondedRequest;
use Crwlr\Crawler\Input;
use Crwlr\Crawler\Steps\Csv;
use Crwlr\Crawler\Steps\FilterRules\Comparison;
use Crwlr\Crawler\Steps\FilterRules\StringCheck;
use Crwlr\Crawler\Steps\Filters\Filter;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Utils;
use InvalidArgumentException;
use stdClass;
use function tests\helper_invokeStepWithInput;
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

    $outputs = helper_invokeStepWithInput(Csv::parseString(['id', 'domain', 'url']), $string);

    expect($outputs)->toHaveCount(4);

    expect($outputs[0]->get())->toBe(['id' => '123', 'domain' => 'crwl.io', 'url' => 'https://www.crwl.io']);

    expect($outputs[1]->get())->toBe(['id' => '234', 'domain' => 'example.com', 'url' => 'https://www.example.com']);

    expect($outputs[2]->get())->toBe(['id' => '345', 'domain' => 'otsch.codes', 'url' => 'https://www.otsch.codes']);

    expect($outputs[3]->get())->toBe(
        ['id' => '456', 'domain' => 'crwlr.software', 'url' => 'https://www.crwlr.software']
    );
});

it('maps a file', function () {
    $outputs = helper_invokeStepWithInput(Csv::parseFile(['id', 'name', 'homepage']), helper_csvFilePath('basic.csv'));

    expect($outputs)->toHaveCount(3);

    expect($outputs[0]->get())->toBe(['id' => '123', 'name' => 'Otsch', 'homepage' => 'https://www.otsch.codes']);

    expect($outputs[1]->get())->toBe(['id' => '234', 'name' => 'John Doe', 'homepage' => 'https://www.john.doe']);

    expect($outputs[2]->get())->toBe(['id' => '345', 'name' => 'Jane Doe', 'homepage' => 'https://www.jane.doe']);
});

it('works with a RequestResponseAggregate as input', function () {
    $body = <<<CSV
        123,"John Doe","+431234567"
        234,"Jane Doe","+432345678"
        CSV;

    $aggregate = new RespondedRequest(new Request('GET', '/'), new Response(200, [], Utils::streamFor($body)));

    $outputs = helper_invokeStepWithInput(Csv::parseString(['id', 'name', 'phone']), $aggregate);

    expect($outputs)->toHaveCount(2);

    expect($outputs[0]->get())->toBe(['id' => '123', 'name' => 'John Doe', 'phone' => '+431234567']);

    expect($outputs[1]->get())->toBe(['id' => '234', 'name' => 'Jane Doe', 'phone' => '+432345678']);
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

    $outputs = helper_invokeStepWithInput(Csv::parseString(['id', 'name', 'phone']), $object);

    expect($outputs)->toHaveCount(2);

    expect($outputs[0]->get())->toBe(['id' => '123', 'name' => 'Max Mustermann', 'phone' => '+431234567']);

    expect($outputs[1]->get())->toBe(['id' => '234', 'name' => 'Julia Musterfrau', 'phone' => '+432345678']);
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

    $outputs = helper_invokeStepWithInput(Csv::parseString([1 => 'domain', 3 => 'description']), $string);

    expect($outputs)->toHaveCount(2);

    expect($outputs[0]->get())->toBe([
        'domain' => 'crwlr.software', 'description' => 'PHP Web Crawling and Scraping Library'
    ]);

    expect($outputs[1]->get())->toBe(['domain' => 'otsch.codes', 'description' => 'I am Otsch, I code']);
});

it('can map columns using numerical array keys for the columns when parsing file', function () {
    $outputs = helper_invokeStepWithInput(
        Csv::parseFile([1 => 'name', 2 => 'homepage']),
        helper_csvFilePath('basic.csv')
    );

    expect($outputs)->toHaveCount(3);

    expect($outputs[0]->get())->toBe(['name' => 'Otsch', 'homepage' => 'https://www.otsch.codes']);

    expect($outputs[1]->get())->toBe(['name' => 'John Doe', 'homepage' => 'https://www.john.doe']);

    expect($outputs[2]->get())->toBe(['name' => 'Jane Doe', 'homepage' => 'https://www.jane.doe']);
});

it('can map columns using null for columns to skip', function () {
    $string = <<<CSV
        1997,Ford,E350,"ac, abs, moon",3000.00
        1999,Chevy,"Venture \"Extended Edition\"","",4900.00
        1999,Chevy,"Venture \"Extended Edition, Very Large\"",,5000.00
        CSV;

    $outputs = helper_invokeStepWithInput(Csv::parseString([null, 'make', null, null, 'price']), $string);

    expect($outputs)->toHaveCount(3);

    expect($outputs[0]->get())->toBe(['make' => 'Ford', 'price' => '3000.00']);

    expect($outputs[1]->get())->toBe(['make' => 'Chevy', 'price' => '4900.00']);

    expect($outputs[2]->get())->toBe(['make' => 'Chevy', 'price' => '5000.00']);
});

it('can map columns using null for columns to skip when parsing file', function () {
    $outputs = helper_invokeStepWithInput(Csv::parseFile(['id', null, 'homepage']), helper_csvFilePath('basic.csv'));

    expect($outputs)->toHaveCount(3);

    expect($outputs[0]->get())->toBe(['id' => '123', 'homepage' => 'https://www.otsch.codes']);

    expect($outputs[1]->get())->toBe(['id' => '234', 'homepage' => 'https://www.john.doe']);

    expect($outputs[2]->get())->toBe(['id' => '345', 'homepage' => 'https://www.jane.doe']);
});

it('skips the first line when defined via method call to skipFirstLine method', function () {
    $string = <<<CSV
        Year,Make,Model,Description,Price
        1997,Ford,E350,"ac, abs, moon",3000.00
        1999,Chevy,"Venture \"Extended Edition\"","",4900.00
        1999,Chevy,"Venture \"Extended Edition, Very Large\"",,5000.00
        CSV;

    $step = Csv::parseString([null, 'make', null, null, 'price'])
        ->skipFirstLine();

    $outputs = helper_invokeStepWithInput($step, $string);

    expect($outputs)->toHaveCount(3);

    expect($outputs[0]->get())->toBe(['make' => 'Ford', 'price' => '3000.00']);
});

it('skips the first line when parsing file when defined via method call to skipFirstLine method', function () {
    $step = Csv::parseFile([1 => 'fach-erste', 2 => 'fach-zweite'])
        ->skipFirstLine();

    $outputs = helper_invokeStepWithInput($step, helper_csvFilePath('with-column-headlines.csv'));

    expect($outputs)->toHaveCount(3);

    expect($outputs[0]->get())->toBe(['fach-erste' => 'Mathematik', 'fach-zweite' => 'Deutsch']);

    expect($outputs[1]->get())->toBe(['fach-erste' => 'Sport', 'fach-zweite' => 'Deutsch']);

    expect($outputs[2]->get())->toBe(['fach-erste' => 'Sport', 'fach-zweite' => 'Religion (ev., kath.)']);
});

it('skips the first line when defined via constructor param', function () {
    $string = <<<CSV
        Year,Make,Model,Description,Price
        1997,Ford,E350,"ac, abs, moon",3000.00
        CSV;

    $outputs = helper_invokeStepWithInput(Csv::parseString([null, 'make', null, null, 'price'], true), $string);

    expect($outputs)->toHaveCount(1);

    expect($outputs[0]->get())->toBe(['make' => 'Ford', 'price' => '3000.00']);
});

it('skips the first line when parsing file when defined via constructor param', function () {
    $outputs = helper_invokeStepWithInput(
        Csv::parseFile([1 => 'fach-erste', 3 => 'fach-dritte'], true),
        helper_csvFilePath('with-column-headlines.csv')
    );

    expect($outputs)->toHaveCount(3);

    expect($outputs[0]->get())->toBe(['fach-erste' => 'Mathematik', 'fach-dritte' => 'Englisch']);

    expect($outputs[1]->get())->toBe(['fach-erste' => 'Sport', 'fach-dritte' => 'Englisch']);

    expect($outputs[2]->get())->toBe(['fach-erste' => 'Sport', 'fach-dritte' => 'Kunst']);
});

it('uses a different separator when you set one', function () {
    $string = <<<CSV
        123|"CoDerOtsch"|Christian|Olear|35
        234|"g3n1u5"|Albert|Einstein|143
        345|"sWiFtY"|Taylor|Swift|32
        CSV;

    $step = Csv::parseString([1 => 'username', 2 => 'firstname', 3 => 'surname'])
        ->separator('|');

    $outputs = helper_invokeStepWithInput($step, $string);

    expect($outputs)->toHaveCount(3);

    expect($outputs[0]->get())->toBe(['username' => 'CoDerOtsch', 'firstname' => 'Christian', 'surname' => 'Olear']);

    expect($outputs[1]->get())->toBe(['username' => 'g3n1u5', 'firstname' => 'Albert', 'surname' => 'Einstein']);

    expect($outputs[2]->get())->toBe(['username' => 'sWiFtY', 'firstname' => 'Taylor', 'surname' => 'Swift']);
});

it('uses a different separator when you set one, when parsing a file', function () {
    $step = Csv::parseFile([1 => 'username', 4 => 'age'])
        ->separator('*');

    $outputs = helper_invokeStepWithInput($step, helper_csvFilePath('separator.csv'));

    expect($outputs)->toHaveCount(3);

    expect($outputs[0]->get())->toBe(['username' => 'CoDerOtsch', 'age' => '35']);

    expect($outputs[1]->get())->toBe(['username' => 'g3n1u5', 'age' => '143']);

    expect($outputs[2]->get())->toBe(['username' => 'sWiFtY', 'age' => '32']);
});

it('throws an InvalidArgumentException when you try to set a multi character separator', function () {
    Csv::parseString([])->separator('***');
})->throws(InvalidArgumentException::class);

it('uses a different enclosure when you set one', function () {
    $string = <<<CSV
        123,/Fritattensuppe/,3.9
        234,/Wiener Schnitzel vom Schwein/,12.7
        345,/Semmelknödel mit Schwammerlsauce/,9.5
        CSV;

    $step = Csv::parseString([1 => 'meal', 2 => 'price'])
        ->enclosure('/');

    $outputs = helper_invokeStepWithInput($step, $string);

    expect($outputs)->toHaveCount(3);

    expect($outputs[0]->get())->toBe(['meal' => 'Fritattensuppe', 'price' => '3.9']);

    expect($outputs[1]->get())->toBe(['meal' => 'Wiener Schnitzel vom Schwein', 'price' => '12.7']);

    expect($outputs[2]->get())->toBe(['meal' => 'Semmelknödel mit Schwammerlsauce', 'price' => '9.5']);
});

it('uses a different enclosure when you set one, when parsing a file', function () {
    $step = Csv::parseFile([1 => 'meal', 2 => 'price'])
        ->enclosure('?');

    $outputs = helper_invokeStepWithInput($step, helper_csvFilePath('enclosure.csv'));

    expect($outputs)->toHaveCount(3);

    expect($outputs[0]->get())->toBe(['meal' => 'Kräftige Rindsuppe', 'price' => '4.5']);

    expect($outputs[1]->get())->toBe(['meal' => 'Crispy Chicken Burger', 'price' => '12']);

    expect($outputs[2]->get())->toBe(['meal' => 'Duett von Saibling und Forelle', 'price' => '21']);
});

it('uses a different escape character when you set one', function () {
    $string = <<<CSV
        123,"test &"escape&" test",test
        CSV;

    $step = Csv::parseString([1 => 'escaped'])
        ->escape('&');

    $outputs = helper_invokeStepWithInput($step, $string);

    expect($outputs)->toHaveCount(1);

    expect($outputs[0]->get())->toBe(['escaped' => 'test &"escape&" test']);
});

it('uses a different escape character when you set one, when parsing a file', function () {
    $step = Csv::parseFile([1 => 'escaped'])
        ->escape('%');

    $outputs = helper_invokeStepWithInput($step, helper_csvFilePath('escape.csv'));

    expect($outputs)->toHaveCount(2);

    expect($outputs[0]->get())->toBe(['escaped' => 'test %"escape%" test']);

    expect($outputs[1]->get())->toBe(['escaped' => 'foo %"escape%" bar %"baz%" lorem']);
});

it('filters rows', function () {
    $string = <<<CSV
        ID,firstname,surname,isPremium
        123,Freddy,Mercury,1
        124,Christian,Olear,1
        125,Jeff,Bezos,0
        CSV;

    $step = Csv::parseString(['id', 3 => 'isPremium'])
        ->skipFirstLine()
        ->where('isPremium', Filter::equal('1'));

    $outputs = helper_invokeStepWithInput($step, $string);

    expect($outputs)->toHaveCount(2);

    expect($outputs[0]->get())->toBe(['id' => '123', 'isPremium' => '1']);

    expect($outputs[1]->get())->toBe(['id' => '124', 'isPremium' => '1']);
});

it('filters rows when parsing a file', function () {
    $step = Csv::parseFile(['Stunde', 'Fach'])
        ->skipFirstLine()
        ->where('Fach', Filter::equal('Sport'));

    $outputs = helper_invokeStepWithInput($step, helper_csvFilePath('with-column-headlines.csv'));

    expect($outputs)->toHaveCount(2);

    expect($outputs[0]->get())->toBe(['Stunde' => '2', 'Fach' => 'Sport']);

    expect($outputs[1]->get())->toBe(['Stunde' => '3', 'Fach' => 'Sport']);
});

it('filters rows by multiple filters', function () {
    $string = <<<CSV
        ID,firstname,surname,isVip,queenBandMember
        123,Freddy,Mercury,1,1
        124,Ozzy,Osbourne,1,0
        125,Barry,Mitchell,0,1
        CSV;

    $step = Csv::parseString(['id', 3 => 'isVip', 4 => 'isQueenBandMember'])
        ->skipFirstLine()
        ->where('isVip', Filter::equal('1'))
        ->where('isQueenBandMember', Filter::equal('1'));

    $outputs = helper_invokeStepWithInput($step, $string);

    expect($outputs)->toHaveCount(1);

    expect($outputs[0]->get())->toBe(['id' => '123', 'isVip' => '1', 'isQueenBandMember' => '1']);
});

it('filters rows by multiple filters when parsing a file', function () {
    $step = Csv::parseFile(['Stunde', 'Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag'])
        ->skipFirstLine()
        ->where('Montag', Filter::equal('Sport'))
        ->where('Donnerstag', Filter::equal('Sport'));

    $outputs = helper_invokeStepWithInput($step, helper_csvFilePath('with-column-headlines.csv'));

    expect($outputs)->toHaveCount(1);

    expect($outputs[0]->get())->toBe([
        'Stunde' => '2',
        'Montag' => 'Sport',
        'Dienstag' => 'Deutsch',
        'Mittwoch' => 'Englisch',
        'Donnerstag' => 'Sport',
        'Freitag' => 'Geschichte',
    ]);
});

it('filters rows with a StringCheck filter', function () {
    $string = <<<CSV
        ID,firstname,surname
        123,Christian,Bale
        124,"Christian Anton",Smith
        125,"Another Christian",Idontknow
        126,Jennifer,Aniston
        CSV;

    $step = Csv::parseString(['id', 'firstname'])
        ->skipFirstLine()
        ->where('firstname', Filter::stringContains('Christian'));

    $outputs = helper_invokeStepWithInput($step, $string);

    expect($outputs)->toHaveCount(3);

    expect($outputs[0]->get())->toBe(['id' => '123', 'firstname' => 'Christian']);

    expect($outputs[1]->get())->toBe(['id' => '124', 'firstname' => 'Christian Anton']);

    expect($outputs[2]->get())->toBe(['id' => '125', 'firstname' => 'Another Christian']);
});
