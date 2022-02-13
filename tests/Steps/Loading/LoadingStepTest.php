<?php

namespace tests\Steps\Loading;

use Crwlr\Crawler\Input;
use Crwlr\Crawler\Loader\HttpLoader;
use Crwlr\Crawler\Steps\Loading\LoadingStep;
use Mockery;

test('You can add a loader', function () {
    $step = new class () extends LoadingStep {
        protected function invoke(Input $input): array
        {
            $this->loader->load($input->get());

            return [];
        }
    };
    $loader = Mockery::mock(HttpLoader::class);
    $loader->shouldReceive('load')->once();
    $step->addLoader($loader);
    $step->invokeStep(new Input('https://www.digitalocean.com/blog'));
});
