<?php

namespace tests;

use Generator;
use GuzzleHttp\Psr7\Response;

function helper_getDummyRobotsTxtResponse(?string $forDomain = null): Response
{
    return new Response(
        200,
        [],
        "User-agent: FooBot\n" .
        "Disallow: " . ($forDomain ? '/' . $forDomain . '/secret' : 'secret')
    );
}

/**
 * @param iterable<mixed> $iterable
 * @return void
 */
function helper_traverseIterable(iterable $iterable): void
{
    foreach ($iterable as $key => $value) {
        // just traverse
    }
}

/**
 * @param Generator<mixed> $generator
 * @return mixed[]
 */
function helper_generatorToArray(Generator $generator): array
{
    $array = [];

    foreach ($generator as $value) {
        $array[] = $value;
    }

    return $array;
}
