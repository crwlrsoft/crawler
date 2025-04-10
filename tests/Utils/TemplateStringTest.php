<?php

namespace tests\Utils;

use Crwlr\Crawler\Utils\TemplateString;

it('resolves the variable syntax in a string with data from an array', function () {
    $string = <<<STRING
        https://www.example.com/[crwl:foo]/bar

        Lorem ipsum [crwl:'asdf'] dolor. Don't replace [crwl.io](/a/markdown/link) this.

        But [crwl:'asdf\'asdf'] this.

        Also with [crwl:"qu\"z"] quotes in it.
        STRING;

    $replaced = TemplateString::resolve($string, [
        'foo' => 'foo',
        'asdf' => 'asdf',
        'var' => 'yolo',
        'asdf\'asdf' => 'replace',
        'qu"z' => 'double',
    ]);

    expect($replaced)->toBe(
        <<<STRING
        https://www.example.com/foo/bar

        Lorem ipsum asdf dolor. Don't replace [crwl.io](/a/markdown/link) this.

        But replace this.

        Also with double quotes in it.
        STRING,
    );
});

it('resolves two variables in one line (regex is non greedy)', function () {
    expect(
        TemplateString::resolve(
            'hi [crwl:"one"]/[crwl:two] bye',
            ['one' => 'bonjour', 'two' => 'ciao'],
        ),
    )->toBe('hi bonjour/ciao bye');
});
