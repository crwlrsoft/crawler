<?php

namespace tests\Steps;

use Crwlr\Crawler\Steps\Dom;
use Crwlr\Crawler\Steps\Html;
use function tests\helper_invokeStepWithInput;

function helper_getHtmlContent(string $fileName): string
{
    $content = file_get_contents(__DIR__ . '/_Files/Html/' . $fileName);

    if ($content === false) {
        return '';
    }

    return $content;
}

it('extracts data from an HTML document with CSS selectors by default', function () {
    $output = helper_invokeStepWithInput(
        Html::each('#bookstore .book')->extract(['title' => '.title', 'author' => '.author', 'year' => '.year']),
        helper_getHtmlContent('bookstore.html')
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

it('can also extract data using XPath queries', function () {
    $output = helper_invokeStepWithInput(
        Html::each(Dom::xPath('//div[@id=\'bookstore\']/div[@class=\'book\']'))->extract([
            'title' => Dom::xPath('//h3[@class=\'title\']'),
            'author' => Dom::xPath('//*[@class=\'author\']'),
            'year' => Dom::xPath('//span[@class=\'year\']'),
        ]),
        helper_getHtmlContent('bookstore.html')
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
        Html::root()->extract(['title' => '.title', 'author' => '.author', 'year' => '.year',]),
        helper_getHtmlContent('bookstore.html')
    );

    expect($output)->toHaveCount(1);

    expect($output[0]->get()['title'])->toBe(['Everyday Italian', 'Harry Potter', 'XQuery Kick Start', 'Learning XML']);
});

it('extracts the data of the first matching element when the first method is used', function () {
    $output = helper_invokeStepWithInput(
        Html::first('#bookstore .book')->extract(['title' => '.title', 'author' => '.author', 'year' => '.year']),
        helper_getHtmlContent('bookstore.html')
    );

    expect($output)->toHaveCount(1);

    expect($output[0]->get())->toBe(
        ['title' => 'Everyday Italian', 'author' => 'Giada De Laurentiis', 'year' => '2005']
    );
});

it('extracts the data of the last matching element when the last method is used', function () {
    $output = helper_invokeStepWithInput(
        Html::last('#bookstore .book')->extract(['title' => '.title', 'author' => '.author', 'year' => '.year']),
        helper_getHtmlContent('bookstore.html')
    );

    expect($output)->toHaveCount(1);

    expect($output[0]->get())->toBe(['title' => 'Learning XML', 'author' => 'Erik T. Ray', 'year' => '2003']);
});
