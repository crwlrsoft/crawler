<?php

namespace tests\Steps\Loading;

use Crwlr\Crawler\Input;
use Crwlr\Crawler\Loader\HttpLoader;
use Crwlr\Crawler\Steps\Loading\LoadingStep;
use Mockery;
use function tests\helper_traverseIterable;

test('You can add a loader', function () {
    $step = new class () extends LoadingStep {
        /**
         * @return mixed[]
         */
        protected function invoke(Input $input): array
        {
            $this->loader->load($input->get());

            return [];
        }
    };
    $loader = Mockery::mock(HttpLoader::class);
    $loader->shouldReceive('load')->once();
    $step->addLoader($loader);
    helper_traverseIterable($step->invokeStep(new Input('https://www.digitalocean.com/blog')));
});
