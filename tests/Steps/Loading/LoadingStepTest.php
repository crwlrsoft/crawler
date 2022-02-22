<?php

namespace tests\Steps\Loading;

use Crwlr\Crawler\Input;
use Crwlr\Crawler\Loader\HttpLoader;
use Crwlr\Crawler\Steps\Loading\LoadingStep;
use Generator;
use Mockery;
use function tests\helper_traverseIterable;

test('You can add a loader', function () {
    $step = new class () extends LoadingStep {
        /**
         * @return Generator<array<mixed>>
         */
        protected function invoke(Input $input): Generator
        {
            $this->loader->load($input->get());
            yield [];
        }
    };
    $loader = Mockery::mock(HttpLoader::class);
    $loader->shouldReceive('load')->once();
    $step->addLoader($loader);
    helper_traverseIterable($step->invokeStep(new Input('https://www.digitalocean.com/blog')));
});
