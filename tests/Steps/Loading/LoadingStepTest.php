<?php

namespace tests\Steps\Loading;

use Crwlr\Crawler\Input;
use Crwlr\Crawler\Loader\Http\HttpLoader;
use Crwlr\Crawler\Steps\Loading\LoadingStep;
use Generator;
use Mockery;

use function tests\helper_traverseIterable;

test('You can add a loader', function () {
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
