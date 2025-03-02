<?php

namespace tests\Steps;

use Crwlr\Crawler\Loader\Http\Messages\RespondedRequest;
use Crwlr\Crawler\Steps\Dom;
use Crwlr\Crawler\Steps\Xml;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;

use function tests\helper_getStepFilesContent;
use function tests\helper_invokeStepWithInput;

it('returns single strings when extract is called with a selector only', function () {
    $output = helper_invokeStepWithInput(
        Xml::each('bookstore book')->extract('title'),
        helper_getStepFilesContent('Xml/bookstore.xml'),
    );

    expect($output)->toHaveCount(4)
        ->and($output[0]->get())->toBe('Everyday Italian')
        ->and($output[3]->get())->toBe('Learning XML');
});

it('extracts data from an XML document with XPath queries per default', function () {
    $output = helper_invokeStepWithInput(
        Xml::each('bookstore book')->extract([
            'title' => 'title',
            'author' => 'author',
            'year' => 'year',
        ]),
        helper_getStepFilesContent('Xml/bookstore.xml'),
    );

    expect($output)->toHaveCount(4)
        ->and($output[0]->get())->toBe(
            ['title' => 'Everyday Italian', 'author' => 'Giada De Laurentiis', 'year' => '2005'],
        )
        ->and($output[1]->get())->toBe(['title' => 'Harry Potter', 'author' => 'J K. Rowling', 'year' => '2005'])
        ->and($output[2]->get())->toBe(
            [
                'title' => 'XQuery Kick Start',
                'author' => ['James McGovern', 'Per Bothner', 'Kurt Cagle', 'James Linn', 'Vaidyanathan Nagarajan'],
                'year' => '2003',
            ],
        )
        ->and($output[3]->get())->toBe(['title' => 'Learning XML', 'author' => 'Erik T. Ray', 'year' => '2003']);
});

it('can also extract data using XPath queries', function () {
    $output = helper_invokeStepWithInput(
        Xml::each(Dom::xPath('//bookstore/book'))->extract([
            'title' => Dom::xPath('//title'),
            'author' => Dom::xPath('//author'),
            'year' => Dom::xPath('//year'),
        ]),
        helper_getStepFilesContent('Xml/bookstore.xml'),
    );

    expect($output)->toHaveCount(4)
        ->and($output[2]->get())->toBe(
            [
                'title' => 'XQuery Kick Start',
                'author' => ['James McGovern', 'Per Bothner', 'Kurt Cagle', 'James Linn', 'Vaidyanathan Nagarajan'],
                'year' => '2003',
            ],
        );
});

it('returns only one (compound) output when the root method is used', function () {
    $output = helper_invokeStepWithInput(
        Xml::root()->extract(['title' => 'title', 'author' => 'author', 'year' => 'year']),
        helper_getStepFilesContent('Xml/bookstore.xml'),
    );

    expect($output)->toHaveCount(1)
        ->and($output[0]->get()['title'])->toBe(['Everyday Italian', 'Harry Potter', 'XQuery Kick Start', 'Learning XML']);
});

it('extracts the data of the first matching element when the first method is used', function () {
    $output = helper_invokeStepWithInput(
        Xml::first('bookstore book')->extract(['title' => 'title', 'author' => 'author', 'year' => 'year']),
        helper_getStepFilesContent('Xml/bookstore.xml'),
    );

    expect($output)->toHaveCount(1)
        ->and($output[0]->get())->toBe(
            ['title' => 'Everyday Italian', 'author' => 'Giada De Laurentiis', 'year' => '2005'],
        );
});

it('extracts the data of the last matching element when the last method is used', function () {
    $output = helper_invokeStepWithInput(
        Xml::last('bookstore book')->extract(['title' => 'title', 'author' => 'author', 'year' => 'year']),
        helper_getStepFilesContent('Xml/bookstore.xml'),
    );

    expect($output)->toHaveCount(1)
        ->and($output[0]->get())->toBe(['title' => 'Learning XML', 'author' => 'Erik T. Ray', 'year' => '2003']);
});

test(
    'you can extract data in a second level to the output array using another Xml step as an element in the mapping ' .
    'array',
    function () {
        $response = new RespondedRequest(
            new Request('GET', 'https://www.example.com/events.xml'),
            new Response(body: helper_getStepFilesContent('Xml/events.xml')),
        );

        $outputs = helper_invokeStepWithInput(
            Xml::each('events event')->extract([
                'title' => 'name',
                'location' => 'location',
                'date' => 'date',
                'talks' => Xml::each('talks talk')->extract([
                    'title' => 'title',
                    'speaker' => 'speaker',
                ]),
            ]),
            $response,
        );

        expect($outputs)->toHaveCount(2)
            ->and($outputs[0]->get())->toBe([
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
                ],
            ])
            ->and($outputs[1]->get())->toBe([
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
                ],
            ]);

    },
);

test(
    'When a child step is nested in the extraction and does not use each(), the extracted value is an array with ' .
    'the keys defined in extract(), rather than an array of such arrays as it would be with each().',
    function () {
        $xml = <<<XML
            <?xml version="1.0" encoding="UTF-8"?>
            <companies>
            <company>
                <name>ABCDEFGmbH</name>
                <founded year="1984">foo</founded>
                <location>
                    <country>Germany</country>
                    <city>Frankfurt</city>
                </location>
            </company>
            <company>
                <name>Saubär GmbH</name>
                <founded year="2014">bar</founded>
                <location>
                    <country>Austria</country>
                    <city>Klagenfurt</city>
                </location>
            </company>
            </companies>
            XML;

        $expectedCompany1 = [
            'name' => 'ABCDEFGmbH',
            'founded' => '1984',
            'location' => ['country' => 'Germany', 'city' => 'Frankfurt'],
        ];

        $expectedCompany2 = [
            'name' => 'Saubär GmbH',
            'founded' => '2014',
            'location' => ['country' => 'Austria', 'city' => 'Klagenfurt'],
        ];

        // With base root()
        $step = Xml::each(Dom::xPath('//companies/company'))->extract([
            'name' => Dom::cssSelector('name')->text(),
            'founded' => Dom::xPath('//founded')->attribute('year'),
            'location' => Xml::root()->extract([
                'country' => Dom::xPath('//location/country')->text(),
                'city' => Dom::cssSelector('location city')->text(),
            ]),
        ]);

        $outputs = helper_invokeStepWithInput($step, $xml);

        expect($outputs)->toHaveCount(2)
            ->and($outputs[0]->get())->toBe($expectedCompany1)
            ->and($outputs[1]->get())->toBe($expectedCompany2);

        // With base first()
        $step = Xml::each(Dom::xPath('//companies/company'))->extract([
            'name' => Dom::cssSelector('name')->text(),
            'founded' => Dom::xPath('//founded')->attribute('year'),
            'location' => Xml::first(Dom::cssSelector('location'))->extract([
                'country' => Dom::xPath('//country')->text(),
                'city' => Dom::cssSelector('city')->text(),
            ]),
        ]);

        $outputs = helper_invokeStepWithInput($step, $xml);

        expect($outputs)->toHaveCount(2)
            ->and($outputs[0]->get())->toBe($expectedCompany1)
            ->and($outputs[1]->get())->toBe($expectedCompany2);

        // With base last()
        $step = Xml::each(Dom::xPath('//companies/company'))->extract([
            'name' => Dom::cssSelector('name')->text(),
            'founded' => Dom::xPath('//founded')->attribute('year'),
            'location' => Xml::last(Dom::cssSelector('location'))->extract([
                'country' => Dom::xPath('//country')->text(),
                'city' => Dom::cssSelector('city')->text(),
            ]),
        ]);

        $outputs = helper_invokeStepWithInput($step, $xml);

        expect($outputs)->toHaveCount(2)
            ->and($outputs[0]->get())->toBe($expectedCompany1)
            ->and($outputs[1]->get())->toBe($expectedCompany2);
    },
);

it('works when the response string starts with an UTF-8 byte order mark character', function () {
    $response = new RespondedRequest(
        new Request('GET', 'https://www.example.com/rss'),
        new Response(body: helper_getStepFilesContent('Xml/rss-with-bom.xml')),
    );

    $outputs = helper_invokeStepWithInput(
        Xml::each('channel item')->extract([
            'url' => 'link',
            'title' => 'title',
        ]),
        $response,
    );

    expect($outputs[0]->get())->toBe([
        'url' => 'https://www.example.com/story/1234567/foo-bar-baz?ref=rss',
        'title' => 'Some title',
    ]);
});

test(
    'when selecting elements with each(), you can reference the element already selected within the each() selector ' .
    'itself, in sub selectors',
    function () {
        $xml = <<<XML
            <?xml version="1.0" encoding="utf-8"?>
            <data>
                <items>
                    <item attr="abc">
                        <id>123</id>
                        <subitems>
                            <subitem>
                                <id>456</id>
                            </subitem>
                        </subitems>
                    </item>
                </items>
            </data>
            XML;

        $response = new RespondedRequest(
            new Request('GET', 'https://www.example.com/foo'),
            new Response(body: $xml),
        );

        $output = helper_invokeStepWithInput(
            Xml::each('data items item')->extract([
                // This is what this test is about. The element already selected in each (item) can be
                // referenced in these child selectors.
                'id' => Dom::cssSelector('item > id'),
                'attribute' => Dom::cssSelector('')->attribute('attr'),
            ]),
            $response,
        );

        expect($output)->toHaveCount(1)
            ->and($output[0]->get())->toBe(['id' => '123', 'attribute' => 'abc']);
    },
);

it('works with tags with camelCase names', function () {
    $xml = <<<XML
        <?xml version="1.0" encoding="utf-8"?>
        <feed>
          <channelName>foo</channelName>
          <channelIdentifier>foo</channelIdentifier>
          <items>
            <item>
              <id>abc-123</id>
              <updated>2024-11-07T11:00:31Z</updated>
              <title>Foo bar baz!</title>
              <someUrl>https://www.example.com/item-1?utm_source=foo&amp;utm_medium=feed-xml</someUrl>
              <foo>
                <baRbaz>test</baRbaz>
              </foo>
            </item>
          </items>
        </feed>
        XML;

    $response = new RespondedRequest(
        new Request('GET', 'https://www.example.com/xml-feed'),
        new Response(body: $xml),
    );

    $outputs = helper_invokeStepWithInput(
        Xml::each(Dom::cssSelector('feed items item'))->extract([
            'title' => 'title',
            'some-url' => 'someUrl',
            'foo-bar-baz' => 'foo baRbaz',
        ]),
        $response,
    );

    expect($outputs[0]->get())->toBe([
        'title' => 'Foo bar baz!',
        'some-url' => 'https://www.example.com/item-1?utm_source=foo&utm_medium=feed-xml',
        'foo-bar-baz' => 'test',
    ]);
})->group('php84');
