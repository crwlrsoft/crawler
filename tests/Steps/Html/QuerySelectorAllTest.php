<?php

namespace tests\Steps\Html;

use Crwlr\Crawler\Input;
use Crwlr\Crawler\Logger\CliLogger;
use Crwlr\Crawler\Steps\Html\QuerySelectorAll;

test('Returns all matching elements', function () {
    $html = <<<HTML
<div class="element">foo</div><div class="element">bar</div><div class="element">baz</div>
HTML;
    $querySelectorStep = new QuerySelectorAll('.element');
    $querySelectorStep->addLogger(new CliLogger());
    $input = new Input($html);
    $results = $querySelectorStep->invokeStep($input);
    expect($results)->toHaveCount(3);
    expect($results[0]->get())->toBe('foo');
    expect($results[1]->get())->toBe('bar');
    expect($results[2]->get())->toBe('baz');
});
