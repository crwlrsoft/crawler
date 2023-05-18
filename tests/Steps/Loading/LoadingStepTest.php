<?php

namespace tests\Steps\Loading;

use Crwlr\Crawler\Input;
use Crwlr\Crawler\Loader\Http\HttpLoader;
use Crwlr\Crawler\Steps\Loading\LoadingStep;
use Generator;
use Mockery;

use function tests\helper_traverseIterable;

test('you can add a loader', function () {
    $step = new class () extends LoadingStep {
        protected function invoke(mixed $input): Generator
        {
            $this->loader->load($input);

            yield [];
        }
    };

    $loader = Mockery::mock(HttpLoader::class);

    $loader->shouldReceive('load')->once();

    $step->addLoader($loader);

    helper_traverseIterable($step->invokeStep(new Input('https://www.digitalocean.com/blog')));
});

test('you can set the key of the loader that it should use', function () {
    $step = new class () extends LoadingStep {
        protected function invoke(mixed $input): Generator
        {
            yield 'yo';
        }
    };

    expect($step->usesLoader())->toBeNull();

    $step->useLoader('ftp');

    expect($step->usesLoader())->toBe('ftp');
});
