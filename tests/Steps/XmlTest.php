<?php

namespace tests\Steps;

use Crwlr\Crawler\Loader\Http\Messages\RespondedRequest;
use Crwlr\Crawler\Steps\Dom;
use Crwlr\Crawler\Steps\Xml;

use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;

use function tests\helper_invokeStepWithInput;

function helper_getXmlContent(string $fileName): string
{
    $content = file_get_contents(__DIR__ . '/_Files/Xml/' . $fileName);

    if ($content === false) {
        return '';
    }

    return $content;
}

it('returns single strings when extract is called with a selector only', function () {
    $output = helper_invokeStepWithInput(
        Xml::each('bookstore/book')->extract('//title'),
        helper_getXmlContent('bookstore.xml')
    );

    expect($output)->toHaveCount(4);

    expect($output[0]->get())->toBe('Everyday Italian');

    expect($output[3]->get())->toBe('Learning XML');
});

it('extracts data from an XML document with XPath queries per default', function () {
    $output = helper_invokeStepWithInput(
        Xml::each('bookstore/book')->extract(['title' => '//title', 'author' => '//author', 'year' => '//year']),
        helper_getXmlContent('bookstore.xml')
    );

    expect($output)->toHaveCount(4);

    expect($output[0]->get())->toBe(
        ['title' => 'Everyday Italian', 'author' => 'Giada De Laurentiis', 'year' => '2005']
    );

    expect($output[1]->get())->toBe(['title' => 'Harry Potter', 'author' => 'J K. Rowling', 'year' => '2005']);

    expect($output[2]->get())->toBe(
        [
            'title' => 'XQuery Kick Start',
            'author' => ['James McGovern', 'Per Bothner', 'Kurt Cagle', 'James Linn', 'Vaidyanathan Nagarajan'],
            'year' => '2003'
        ]
    );

    expect($output[3]->get())->toBe(['title' => 'Learning XML', 'author' => 'Erik T. Ray', 'year' => '2003']);
});

it('can also extract data using CSS selectors', function () {
    $output = helper_invokeStepWithInput(
        Xml::each(Dom::cssSelector('bookstore book'))->extract([
            'title' => Dom::cssSelector('title'),
            'author' => Dom::cssSelector('author'),
            'year' => Dom::cssSelector('year'),
        ]),
        helper_getXmlContent('bookstore.xml')
    );

    expect($output)->toHaveCount(4);

    expect($output[2]->get())->toBe(
        [
            'title' => 'XQuery Kick Start',
            'author' => ['James McGovern', 'Per Bothner', 'Kurt Cagle', 'James Linn', 'Vaidyanathan Nagarajan'],
            'year' => '2003'
        ]
    );
});

it('returns only one (compound) output when the root method is used', function () {
    $output = helper_invokeStepWithInput(
        Xml::root()->extract(['title' => '//title', 'author' => '//author', 'year' => '//year']),
        helper_getXmlContent('bookstore.xml')
    );

    expect($output)->toHaveCount(1);

    expect($output[0]->get()['title'])->toBe(['Everyday Italian', 'Harry Potter', 'XQuery Kick Start', 'Learning XML']);
});

it('extracts the data of the first matching element when the first method is used', function () {
    $output = helper_invokeStepWithInput(
        Xml::first('bookstore/book')->extract(['title' => '//title', 'author' => '//author', 'year' => '//year']),
        helper_getXmlContent('bookstore.xml')
    );

    expect($output)->toHaveCount(1);

    expect($output[0]->get())->toBe(
        ['title' => 'Everyday Italian', 'author' => 'Giada De Laurentiis', 'year' => '2005']
    );
});

it('extracts the data of the last matching element when the last method is used', function () {
    $output = helper_invokeStepWithInput(
        Xml::last('bookstore/book')->extract(['title' => '//title', 'author' => '//author', 'year' => '//year']),
        helper_getXmlContent('bookstore.xml')
    );

    expect($output)->toHaveCount(1);

    expect($output[0]->get())->toBe(['title' => 'Learning XML', 'author' => 'Erik T. Ray', 'year' => '2003']);
});

test(
    'you can extract data in a second level to the output array using another Xml step as an element in the mapping ' .
    'array',
    function () {
        $response = new RespondedRequest(
            new Request('GET', 'https://www.example.com/events.xml'),
            new Response(body: helper_getXmlContent('events.xml'))
        );

        $outputs = helper_invokeStepWithInput(
            Xml::each('//events/event')->extract([
                'title' => '//name',
                'location' => '//location',
                'date' => '//date',
                'talks' => Xml::each('//talks/talk')->extract([
                    'title' => '//title',
                    'speaker' => '//speaker',
                ])
            ]),
            $response,
        );

        expect($outputs)->toHaveCount(2);

        expect($outputs[0]->get())->toBe([
            'title' => 'Some Meetup',
            'location' => 'Somewhere',
            'date' => '2023-01-14 20:00',
            'talks' => [
                [
                    'title' => 'Sophisticated talk title',
                    'speaker' => 'Super Mario',
                ],
                [
                    'title' => 'Fun talk',
                    'speaker' => 'Princess Peach',
                ],
            ]
        ]);

        expect($outputs[1]->get())->toBe([
            'title' => 'Another Meetup',
            'location' => 'Somewhere else',
            'date' => '2023-01-21 19:00',
            'talks' => [
                [
                    'title' => 'Join the dark side',
                    'speaker' => 'Wario',
                ],
                [
                    'title' => 'Let\'s go',
                    'speaker' => 'Yoshi',
                ],
            ]
        ]);
    }
);
