<?php

namespace tests\Stores;

use Crwlr\Crawler\Result;
use Crwlr\Crawler\Stores\SimpleCsvFileStore;

/**
 * @param mixed[] $data
 */
function helper_getResultWithData(array $data): Result
{
    $result = new Result();

    foreach ($data as $key => $value) {
        $result->set($key, $value);
    }

    return $result;
}

beforeAll(function () {
    if (!file_exists(__DIR__ . '/_files')) {
        mkdir(__DIR__ . '/_files');
    }
});

it('saves Results to a csv file', function () {
    $result1 = helper_getResultWithData(['user' => 'otsch', 'firstname' => 'Christian', 'surname' => 'Olear']);

    $store = new SimpleCsvFileStore(__DIR__ . '/_files', 'test');

    $store->store($result1);

    expect(file_get_contents($store->filePath()))->toBe("user,firstname,surname\notsch,Christian,Olear\n");

    $result2 = helper_getResultWithData(['user' => 'hader', 'firstname' => 'Josef', 'surname' => 'Hader']);

    $store->store($result2);

    expect(file_get_contents($store->filePath()))->toBe(
        "user,firstname,surname\notsch,Christian,Olear\nhader,Josef,Hader\n"
    );

    $result3 = helper_getResultWithData(['user' => 'evamm', 'firstname' => 'Eva Maria', 'surname' => 'Maier']);

    $store->store($result3);

    expect(file_get_contents($store->filePath()))->toBe(
        "user,firstname,surname\notsch,Christian,Olear\nhader,Josef,Hader\nevamm,\"Eva Maria\",Maier\n"
    );
});

test('if the value of a result property is an array, it concatenates the values separated with a pipe', function () {
    $result1 = helper_getResultWithData(['col1' => 'foo', 'col2' => ['bar', 'baz', 'quz']]);

    $store = new SimpleCsvFileStore(__DIR__ . '/_files', 'test2');

    $store->store($result1);

    expect(file_get_contents($store->filePath()))->toBe("col1,col2\nfoo,\"bar | baz | quz\"\n");

    $result2 = helper_getResultWithData(['col1' => 'Donald', 'col2' => ['Tick', 'Trick', 'Track']]);

    $store->store($result2);

    expect(file_get_contents($store->filePath()))->toBe(
        "col1,col2\nfoo,\"bar | baz | quz\"\nDonald,\"Tick | Trick | Track\"\n"
    );
});

afterAll(function () {
    $dir = __DIR__ . '/_files';

    if (file_exists($dir)) {
        $files = scandir($dir);

        if (is_array($files)) {
            foreach ($files as $file) {
                if ($file === '.' || $file === '..') {
                    continue;
                }

                unlink($dir . '/' . $file);
            }
        }

        rmdir(__DIR__ . '/_files');
    }
});
