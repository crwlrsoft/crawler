<?php

namespace tests;

use Crwlr\Crawler\Result;
use Crwlr\Crawler\Results;

/**
 * @param mixed[] $data
 */
function helper_buildResultWithData(string $name, array $data): Result
{
    $result = new Result();

    foreach ($data as $key => $value) {
        if (is_array($value)) {
            foreach ($value as $v) {
                $result->set($key, $v);
            }
        } else {
            $result->set($key, $value);
        }
    }

    return $result;
}

test('You can convert the whole Results collection to a flat array', function () {
    $result1 = helper_buildResultWithData(
        'JobAd',
        ['title' => 'PHP Web Developer (w/m/x)', 'location' => ['Linz', 'Wien']]
    );
    $result2 = helper_buildResultWithData(
        'JobAd',
        ['title' => 'CEO (w/m/x)', 'location' => ['London', 'Paris']]
    );
    $result3 = helper_buildResultWithData(
        'JobAd',
        ['title' => 'Controller (w/m/x)', 'location' => ['London', 'Paris']]
    );
    $results = new Results([$result1, $result2, $result3]);

    expect($results->allToArray())->toBe([
        ['title' => 'PHP Web Developer (w/m/x)', 'location' => ['Linz', 'Wien']],
        ['title' => 'CEO (w/m/x)', 'location' => ['London', 'Paris']],
        ['title' => 'Controller (w/m/x)', 'location' => ['London', 'Paris']],
    ]);
});
