<?php

namespace tests\Stores;

use Crwlr\Crawler\Result;
use Crwlr\Crawler\Stores\JsonFileStore;

/**
 * @param mixed[] $data
 */
function helper_getResultWithJsonData(array $data): Result
{
    $result = new Result();

    foreach ($data as $key => $value) {
        $result->set($key, $value);
    }

    return $result;
}

it('saves Results to a JSON file', function () {
    $result1 = helper_getResultWithJsonData(['user' => 'otsch', 'firstname' => 'Christian', 'surname' => 'Olear']);

    $store = new JsonFileStore(__DIR__ . '/_files', 'test');

    $store->store($result1);

    expect(file_get_contents($store->filePath()))->toBe('[{"user":"otsch","firstname":"Christian","surname":"Olear"}]');

    $result2 = helper_getResultWithJsonData(['user' => 'hader', 'firstname' => 'Josef', 'surname' => 'Hader']);

    $store->store($result2);

    expect(file_get_contents($store->filePath()))->toBe(
        '[{"user":"otsch","firstname":"Christian","surname":"Olear"},' .
        '{"user":"hader","firstname":"Josef","surname":"Hader"}]'
    );

    $result3 = helper_getResultWithJsonData(['user' => 'evamm', 'firstname' => 'Eva Maria', 'surname' => 'Maier']);

    $store->store($result3);

    expect(file_get_contents($store->filePath()))->toBe(
        '[{"user":"otsch","firstname":"Christian","surname":"Olear"},' .
        '{"user":"hader","firstname":"Josef","surname":"Hader"},' .
        '{"user":"evamm","firstname":"Eva Maria","surname":"Maier"}]'
    );
});

afterAll(function () {
    $dir = __DIR__ . '/_files';

    if (file_exists($dir)) {
        $files = scandir($dir);

        if (is_array($files)) {
            foreach ($files as $file) {
                if ($file === '.' || $file === '..' || !str_ends_with($file, '.json')) {
                    continue;
                }

                @unlink($dir . '/' . $file);
            }
        }
    }
});
