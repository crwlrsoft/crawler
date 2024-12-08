<?php

namespace tests\Steps;

use Crwlr\Crawler\Loader\Http\Messages\RespondedRequest;
use Crwlr\Crawler\Steps\Dom;
use Crwlr\Crawler\Steps\Html;
use Crwlr\Crawler\Steps\Html\GetLink;
use Crwlr\Crawler\Steps\Html\GetLinks;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;

use function tests\helper_invokeStepWithInput;

function helper_getHtmlContent(string $fileName): string
{
    $content = file_get_contents(__DIR__ . '/_Files/Html/' . $fileName);

    if ($content === false) {
        return '';
    }

    return $content;
}

it('returns single strings when extract is called with a selector only', function () {
    $output = helper_invokeStepWithInput(
        Html::each('#bookstore .book')->extract('.title'),
        helper_getHtmlContent('bookstore.html'),
    );

    expect($output)->toHaveCount(4)
        ->and($output[0]->get())->toBe('Everyday Italian')
        ->and($output[3]->get())->toBe('Learning XML');
});

it('extracts data from an HTML document with CSS selectors by default', function () {
    $output = helper_invokeStepWithInput(
        Html::each('#bookstore .book')->extract(['title' => '.title', 'author' => '.author', 'year' => '.year']),
        helper_getHtmlContent('bookstore.html'),
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
        Html::each(Dom::xPath('//div[@id=\'bookstore\']/div[@class=\'book\']'))->extract([
            'title' => Dom::xPath('//h3[@class=\'title\']'),
            'author' => Dom::xPath('//*[@class=\'author\']'),
            'year' => Dom::xPath('//span[@class=\'year\']'),
        ]),
        helper_getHtmlContent('bookstore.html'),
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
        Html::root()->extract(['title' => '.title', 'author' => '.author', 'year' => '.year',]),
        helper_getHtmlContent('bookstore.html'),
    );

    expect($output)->toHaveCount(1)
        ->and($output[0]->get()['title'])->toBe(['Everyday Italian', 'Harry Potter', 'XQuery Kick Start', 'Learning XML']);
});

it('extracts the data of the first matching element when the first method is used', function () {
    $output = helper_invokeStepWithInput(
        Html::first('#bookstore .book')->extract(['title' => '.title', 'author' => '.author', 'year' => '.year']),
        helper_getHtmlContent('bookstore.html'),
    );

    expect($output)->toHaveCount(1)
        ->and($output[0]->get())->toBe(
            ['title' => 'Everyday Italian', 'author' => 'Giada De Laurentiis', 'year' => '2005'],
        );
});

it('extracts the data of the last matching element when the last method is used', function () {
    $output = helper_invokeStepWithInput(
        Html::last('#bookstore .book')->extract(['title' => '.title', 'author' => '.author', 'year' => '.year']),
        helper_getHtmlContent('bookstore.html'),
    );

    expect($output)->toHaveCount(1)
        ->and($output[0]->get())->toBe(['title' => 'Learning XML', 'author' => 'Erik T. Ray', 'year' => '2003']);
});

test(
    'you can extract data in a second level to the output array using another Html step as an element in the mapping ' .
    'array',
    function () {
        $response = new RespondedRequest(
            new Request('GET', 'https://www.example.com/meetups/some-meetup/'),
            new Response(body: helper_getHtmlContent('event.html')),
        );

        $output = helper_invokeStepWithInput(
            Html::root()->extract([
                'title' => '#event h1',
                'location' => '#event .location',
                'date' => '#event .date',
                'talks' => Html::each('#event .talks .talk')->extract([
                    'title' => '.title',
                    'speaker' => '.speaker',
                    'slides' => Dom::cssSelector('.slidesLink')->attribute('href')->toAbsoluteUrl(),
                ]),
            ]),
            $response,
        );

        expect($output)->toHaveCount(1)
            ->and($output[0]->get())->toBe([
                'title' => 'Some Meetup',
                'location' => 'Somewhere',
                'date' => '2023-01-14 21:00',
                'talks' => [
                    [
                        'title' => 'Sophisticated talk title',
                        'speaker' => 'Super Mario',
                        'slides' => 'https://www.example.com/meetups/some-meetup/slides/talk1.pdf',
                    ],
                    [
                        'title' => 'Simple beginner talk',
                        'speaker' => 'Luigi',
                        'slides' => 'https://www.example.com/meetups/some-meetup/slides/talk2.pdf',
                    ],
                    [
                        'title' => 'Fun talk',
                        'speaker' => 'Princess Peach',
                        'slides' => 'https://www.example.com/meetups/some-meetup/slides/talk3.pdf',
                    ],
                ],
            ]);
    },
);

test('the static getLink method works without argument', function () {
    expect(Html::getLink())->toBeInstanceOf(GetLink::class);
});

test('the static getLinks method works without argument', function () {
    expect(Html::getLinks())->toBeInstanceOf(GetLinks::class);
});
